<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaPurpose;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCustomPageRequest;
use App\Models\ContentMedia;
use App\Models\CustomPage;
use App\Services\Content\ContentMediaStorage;
use App\Services\Content\CustomPageHtmlSanitizer;
use App\Services\Platform\AdminAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminCustomPageController extends Controller
{
    public function __construct(
        private readonly CustomPageHtmlSanitizer $sanitizer,
        private readonly AdminAudit $audit,
        private readonly ContentMediaStorage $storage,
    ) {}

    public function assetOptions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $selected = (int) $request->query('selected', 0);
        $items = ContentMedia::query()
            ->where('purpose', MediaPurpose::PlatformAsset)
            ->whereNull('workspace_id')->whereNull('expires_at')
            ->where('visibility', 'private')
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->whereIn('scan_state', ['clean', 'not_required'])
            ->where(function ($builder) use ($query, $selected): void {
                $builder->where('original_name', 'like', '%'.$query.'%')
                    ->orWhere('display_name', 'like', '%'.$query.'%');
                if ($selected > 0) {
                    $builder->orWhere('id', $selected);
                }
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$selected])
            ->orderByDesc('id')->limit(50)->get();

        return response()->json(['items' => $items->map(fn (ContentMedia $media): array => [
            'value' => (string) $media->id,
            'label' => $media->displayName(),
            'description' => $media->mime_type,
        ])->values()]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::in(['draft', 'published'])],
            'placement' => ['nullable', Rule::in(['header', 'footer'])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $query = CustomPage::query()->with(['creator:id,name', 'updater:id,name']);

        if ($search !== '') {
            $query->where(fn ($pages) => $pages
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%'));
        }

        if (($filters['state'] ?? null) !== null) {
            $query->where('state', $filters['state']);
        }

        if (($filters['placement'] ?? null) !== null) {
            $query->where('placement', $filters['placement']);
        }

        return Inertia::render('admin/pages/index', [
            'pages' => $query
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (CustomPage $page): array => [
                    'id' => $page->id,
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'state' => $page->state,
                    'placement' => $page->placement,
                    'creator' => $page->creator?->name,
                    'updater' => $page->updater?->name,
                    'publishedAt' => $page->published_at?->toISOString(),
                    'updatedAt' => $page->updated_at?->toISOString(),
                ]),
            'filters' => [
                'q' => $search,
                'state' => $filters['state'] ?? null,
                'placement' => $filters['placement'] ?? null,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/pages/form', [
            'page' => null,
        ]);
    }

    public function store(AdminCustomPageRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);
        $data = $this->sanitized($request);

        $page = CustomPage::query()->create([
            ...$data,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'published_at' => $data['state'] === 'published' ? now() : null,
        ]);

        $this->audit->record($actor, PlatformPermission::AdminPagesManage, 'create_custom_page', $page, null, [
            'slug' => $page->slug,
            'state' => $page->state,
        ]);

        return to_route('admin.pages.edit', $page);
    }

    public function edit(CustomPage $customPage): Response
    {
        return Inertia::render('admin/pages/form', [
            'page' => [
                'id' => $customPage->id,
                'slug' => $customPage->slug,
                'title' => $customPage->title,
                'body' => $customPage->body,
                'state' => $customPage->state,
                'placement' => $customPage->placement,
                'seo_title' => $customPage->seo_title,
                'seo_description' => $customPage->seo_description,
                'og_media_id' => $customPage->og_media_id,
                'published_at' => $customPage->published_at?->toISOString(),
            ],
        ]);
    }

    public function update(AdminCustomPageRequest $request, CustomPage $customPage): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);
        $data = $this->sanitized($request);

        DB::transaction(function () use ($customPage, $data, $actor): void {
            $locked = CustomPage::query()->lockForUpdate()->findOrFail($customPage->getKey());
            /** @var CustomPage $locked */
            $locked->forceFill([
                ...$data,
                'updated_by' => $actor->id,
                'published_at' => $data['state'] === 'published'
                    ? ($locked->published_at ?? now())
                    : null,
            ])->save();

            $this->audit->record($actor, PlatformPermission::AdminPagesManage, 'update_custom_page', $locked, null, [
                'slug' => $locked->slug,
                'state' => $locked->state,
            ]);
        });

        return to_route('admin.pages.edit', $customPage);
    }

    public function publish(Request $request, CustomPage $customPage): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);

        $customPage->forceFill([
            'state' => 'published',
            'published_at' => $customPage->published_at ?? now(),
            'updated_by' => $actor->id,
        ])->save();

        $this->audit->record($actor, PlatformPermission::AdminPagesManage, 'publish_custom_page', $customPage);

        return back(fallback: route('admin.pages.index'));
    }

    public function destroy(Request $request, CustomPage $customPage): RedirectResponse
    {
        $actor = $request->user();
        abort_if($actor === null, 403);
        $mediaId = $customPage->og_media_id === null ? null : (int) $customPage->og_media_id;

        DB::transaction(function () use ($customPage, $actor): void {
            $locked = CustomPage::query()->lockForUpdate()->findOrFail($customPage->getKey());
            /** @var CustomPage $locked */
            $this->audit->record($actor, PlatformPermission::AdminPagesManage, 'delete_custom_page', $locked);
            $locked->delete();
        });

        if ($mediaId !== null && CustomPage::query()->where('og_media_id', $mediaId)->doesntExist()) {
            $this->storage->deleteIfUnreferencedPlatformAsset($mediaId);
        }

        return back(fallback: route('admin.pages.index'));
    }

    public function preview(CustomPage $customPage): Response
    {
        return Inertia::render('content/show', [
            'page' => [
                'slug' => $customPage->slug,
                'title' => $customPage->title,
                'body' => $this->sanitizer->sanitize($customPage->body),
                'state' => $customPage->state,
                'seoTitle' => $customPage->seo_title,
                'seoDescription' => $customPage->seo_description,
                'publishedAt' => $customPage->published_at?->toISOString(),
            ],
            'preview' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitized(AdminCustomPageRequest $request): array
    {
        $data = $request->validated();
        $data['body'] = $this->sanitizer->sanitize((string) $data['body']);

        if (trim(strip_tags($data['body'])) === '') {
            throw ValidationException::withMessages([
                'body' => 'The page body must contain safe text.',
            ]);
        }

        return $data;
    }
}
