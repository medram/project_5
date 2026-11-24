<?php

namespace App\Http\Controllers;

use App\Enums\MediaPurpose;
use App\Http\Middleware\ScopedAdminContent;
use App\Http\Requests\StoreLinkPageBlockRequest;
use App\Models\ContentMedia;
use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\LinkPageLead;
use App\Models\LinkPageVariant;
use App\Models\Workspace;
use App\Services\Content\ContentMediaStorage;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LinkPageBlockController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaces,
        private readonly ContentMediaStorage $media,
    ) {}

    public function store(StoreLinkPageBlockRequest $request, LinkPage $linkPage): RedirectResponse
    {
        $workspace = $this->authorizePage($request, $linkPage, 'update');
        $data = $request->validated();
        $variant = $this->variantForPage(
            $linkPage,
            isset($data['link_page_variant_id']) ? (int) $data['link_page_variant_id'] : null,
        );
        $data['link_page_variant_id'] = $variant->id;
        $uploadField = $data['type'] === 'file' ? 'file' : 'image';
        $mediaPurpose = $data['type'] === 'file' ? MediaPurpose::File : MediaPurpose::Image;
        $file = $request->file($uploadField);
        $block = $linkPage->blocks()->create([
            ...$this->attributes($data),
            'position' => max(
                (int) ($linkPage->items()->where('link_page_variant_id', $variant->id)->max('position') ?? -1),
                (int) ($linkPage->blocks()->where('link_page_variant_id', $variant->id)->max('position') ?? -1),
            ) + 1,
        ]);

        if ($file) {
            $media = null;
            try {
                $media = $this->media->store($file, $workspace, $request->user(), $mediaPurpose);
                $block->update(['content_media_id' => $media->id]);
            } catch (Throwable $exception) {
                if ($media && ! $request->attributes->has(ScopedAdminContent::ATTRIBUTE)) {
                    $this->media->delete($media);
                }
                $block->delete();
                throw $exception;
            }
        }

        return $this->editorResponse($linkPage, $variant);
    }

    public function update(
        StoreLinkPageBlockRequest $request,
        LinkPage $linkPage,
        LinkPageBlock $linkPageBlock,
    ): RedirectResponse {
        $workspace = $this->authorizePage($request, $linkPage, 'update');
        abort_unless($linkPageBlock->link_page_id === $linkPage->id, 404);

        $data = $request->validated();
        $variant = $this->variantForPage(
            $linkPage,
            isset($data['link_page_variant_id'])
                ? (int) $data['link_page_variant_id']
                : $linkPageBlock->link_page_variant_id,
        );
        $data['link_page_variant_id'] = $variant->id;
        $uploadField = $data['type'] === 'file' ? 'file' : 'image';
        $mediaPurpose = $data['type'] === 'file' ? MediaPurpose::File : MediaPurpose::Image;
        $file = $request->file($uploadField);
        $newMedia = $file
            ? $this->media->store($file, $workspace, $request->user(), $mediaPurpose)
            : null;

        try {
            $result = DB::transaction(function () use ($data, $linkPage, $linkPageBlock, $newMedia): ?ContentMedia {
                $block = LinkPageBlock::query()
                    ->with('contentMedia')
                    ->lockForUpdate()
                    ->findOrFail($linkPageBlock->id);
                abort_unless($block->link_page_id === $linkPage->id, 404);

                $attributes = $this->attributes($data, $block);
                if ((int) $block->link_page_variant_id !== (int) $data['link_page_variant_id']) {
                    $attributes['position'] = max(
                        (int) ($linkPage->items()->where('link_page_variant_id', $data['link_page_variant_id'])->max('position') ?? -1),
                        (int) ($linkPage->blocks()->where('link_page_variant_id', $data['link_page_variant_id'])->max('position') ?? -1),
                    ) + 1;
                }
                if ($newMedia) {
                    $attributes['content_media_id'] = $newMedia->id;
                } elseif (! in_array($data['type'], ['image', 'file'], true)) {
                    $attributes['content_media_id'] = null;
                }

                $hasUnexpiredLeads = $block->type === 'form'
                    && LinkPageLead::query()
                        ->where('link_page_block_id', $block->id)
                        ->where('retention_expires_at', '>', now('UTC'))
                        ->exists();
                if ($hasUnexpiredLeads && $attributes['type'] !== 'form') {
                    throw ValidationException::withMessages(['type' => 'This block cannot change type while its responses are retained.']);
                }
                if ($hasUnexpiredLeads && $attributes['content'] !== $block->content) {
                    throw ValidationException::withMessages(['fields' => 'Keep this form unchanged while its responses are retained.']);
                }

                $oldMedia = $block->contentMedia;
                $block->fill($attributes)->save();

                return $oldMedia;
            });
        } catch (Throwable $exception) {
            if ($newMedia && ! $request->attributes->has(ScopedAdminContent::ATTRIBUTE)) {
                $this->media->delete($newMedia);
            }
            throw $exception;
        }

        if ($result && ($newMedia || ! in_array($data['type'], ['image', 'file'], true))) {
            $this->deleteMedia($request, $result);
        }

        return $this->editorResponse($linkPage, $variant);
    }

    public function destroy(Request $request, LinkPage $linkPage, LinkPageBlock $linkPageBlock): RedirectResponse
    {
        $this->authorizePage($request, $linkPage, 'update');
        abort_unless($linkPageBlock->link_page_id === $linkPage->id, 404);

        if ($linkPageBlock->contentMedia) {
            $this->deleteMedia($request, $linkPageBlock->contentMedia);
        }
        $linkPageBlock->delete();

        return $this->editorResponse(
            $linkPage,
            $this->variantForPage(
                $linkPage,
                $linkPageBlock->link_page_variant_id === null
                    ? null
                    : (int) $linkPageBlock->link_page_variant_id,
            ),
        );
    }

    public function imagePreview(
        Request $request,
        LinkPage $linkPage,
        LinkPageBlock $linkPageBlock,
    ): StreamedResponse {
        $workspace = $this->authorizePage($request, $linkPage, 'view');
        abort_unless($linkPageBlock->link_page_id === $linkPage->id && $linkPageBlock->type === 'image', 404);

        return $this->streamImage($this->imageMedia($linkPageBlock, (int) $workspace->id));
    }

    private function variantForPage(LinkPage $linkPage, ?int $variantId): LinkPageVariant
    {
        $variant = $variantId === null
            ? $linkPage->controlVariant()->where('is_active', true)->first()
            : $linkPage->variants()->whereKey($variantId)->first();
        if ($variant === null) {
            throw ValidationException::withMessages([
                'link_page_variant_id' => 'Select a variant that belongs to this page.',
            ]);
        }

        return $variant;
    }

    private function editorResponse(LinkPage $linkPage, LinkPageVariant $variant): RedirectResponse
    {
        return $variant->is_control
            ? to_route('link-pages.show', $linkPage)
            : to_route('link-pages.show', [
                'linkPage' => $linkPage,
                'variant' => $variant->id,
            ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?LinkPageBlock $existing = null): array
    {
        $type = $data['type'];
        $content = match ($type) {
            'text' => ['text' => $data['text']],
            'image' => ['alt_text' => $data['alt_text'] ?? $existing?->content['alt_text'] ?? ''],
            'form' => ['fields' => array_map(
                fn (array $field): array => [
                    'id' => $field['id'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'required' => filter_var($field['required'], FILTER_VALIDATE_BOOLEAN),
                    ...($field['type'] === 'select' ? ['options' => $field['options']] : []),
                ],
                $data['fields'],
            )],
            'file' => [],
            default => [],
        };

        return [
            'link_page_variant_id' => (int) $data['link_page_variant_id'],
            'type' => $type,
            'content' => $content,
            'is_active' => $data['is_active'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ];
    }

    private function authorizePage(Request $request, LinkPage $linkPage, string $ability): Workspace
    {
        $workspace = $this->workspaces->forContentRequest($request);
        abort_unless((int) $linkPage->workspace_id === (int) $workspace->id, 404);
        app(AdminContentAccess::class)->authorize($request, $ability, $linkPage);

        return $workspace;
    }

    private function deleteMedia(Request $request, ContentMedia $media): void
    {
        if ($request->attributes->has(ScopedAdminContent::ATTRIBUTE)) {
            DB::afterCommit(fn () => $this->media->delete($media));
        } else {
            $this->media->delete($media);
        }
    }

    private function imageMedia(LinkPageBlock $block, int $workspaceId): ContentMedia
    {
        $media = $block->contentMedia()
            ->where('workspace_id', $workspaceId)
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC')))
            ->where('visibility', 'private')
            ->whereIn('scan_state', ['not_required', 'clean'])
            ->firstOrFail();
        abort_unless(in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $media;
    }

    private function streamImage(ContentMedia $media): StreamedResponse
    {
        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return response()->stream(function () use ($disk, $media): void {
            $stream = $disk->readStream($media->path);
            if (! is_resource($stream)) {
                abort(404);
            }
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $media->mime_type,
            'Content-Length' => (string) $media->byte_size,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
