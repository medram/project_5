<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Models\Link;
use App\Services\Accounts\AccountProfileOptions;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Links\LinkAttributes;
use App\Services\Links\LinkRevisionService;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    public function __construct(
        private readonly EnforcesPlanLimits $planLimits,
        private readonly WorkspaceContext $workspaceContext,
        private readonly LinkAttributes $linkAttributes,
        private readonly LinkRevisionService $revisions,
        private readonly AccountProfileOptions $accountProfileOptions,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'tag' => ['nullable', 'integer', Rule::exists('link_tags', 'id')->where('workspace_id', $workspace->id)],
            'status' => ['nullable', 'in:active,inactive,archived,pending'],
            'sort' => ['nullable', 'in:created_at,title,clicks_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:table,cards'],
        ]);
        $filters = [
            'search' => (string) ($filters['search'] ?? ''),
            'folder' => (string) ($filters['folder'] ?? ''),
            'tag' => (string) ($filters['tag'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'sort' => $filters['sort'] ?? 'created_at',
            'direction' => $filters['direction'] ?? 'desc',
            'page' => (string) ($filters['page'] ?? ''),
            'view' => $filters['view'] ?? 'table',
        ];
        $links = $workspace->links()->with(['folder:id,name', 'tags:id,name,slug,color'])
            ->with('customDomain:id,hostname,status')
            ->with('linkOverlay:id,name')
            ->with(['linkPage' => fn ($query) => $query
                ->select(['id', 'name', 'slug'])
                ->where('workspace_id', $workspace->id)])
            ->with('routingRules')
            ->withExists(['revisions as has_pending_revision' => fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', 'pending')])
            ->withCount('clicks');

        if ($filters['search'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $links->where(fn ($query) => $query
                ->whereRaw("title LIKE ? ESCAPE '!' ", [$pattern])
                ->orWhereRaw("slug LIKE ? ESCAPE '!' ", [$pattern]));
        }
        if ($filters['folder'] !== '') {
            $links->where('folder_id', $filters['folder']);
        }
        if ($filters['tag'] !== '') {
            $links->whereHas('tags', fn ($query) => $query->whereKey($filters['tag']));
        }
        if ($filters['status'] === 'archived') {
            $links->whereNotNull('archived_at');
        } elseif ($filters['status'] === 'pending') {
            $links->whereNull('archived_at')->where(function ($query) use ($workspace): void {
                $query->where('approval_status', 'pending')
                    ->orWhereHas('revisions', fn ($revisionQuery) => $revisionQuery
                        ->where('workspace_id', $workspace->id)
                        ->where('status', 'pending'));
            });
        } elseif (in_array($filters['status'], ['active', 'inactive'], true)) {
            $links->whereNull('archived_at')
                ->where('approval_status', 'approved')
                ->where('is_active', $filters['status'] === 'active');
        }

        $links->orderBy($filters['sort'], $filters['direction'])->orderBy('id');

        return Inertia::render('links/index', [
            'links' => $links->paginate(20)->withQueryString(),
            'folders' => $workspace->folders()->orderBy('name')->get(['id', 'name']),
            'tagOptions' => $workspace->linkTags()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'customDomains' => $workspace->customDomains()
                ->where('status', 'active')->orderBy('hostname')->get(['id', 'hostname']),
            'linkOverlays' => $workspace->linkOverlays()
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'linkPages' => $workspace->linkPages()
                ->where('is_published', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'countries' => $this->accountProfileOptions->countries(),
            'canManageApprovals' => Gate::allows('manageLinkApprovals', $workspace),
            'filters' => $filters,
        ]);
    }

    public function store(StoreLinkRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $validated = $request->validated();
        $this->planLimits->create(
            $workspace,
            PlanMetric::Links,
            function ($workspace) use ($request, $validated): Link {
                $link = $workspace->links()->create([
                    ...$this->linkAttributes->fromRequest($request),
                    'user_id' => $request->user()->id,
                ]);
                $this->revisions->recordInitial(
                    $link,
                    $validated['tag_ids'] ?? [],
                    $validated['routing_rules'] ?? [],
                    $request->user(),
                );

                return $link;
            },
        );

        return to_route('links.index', $this->listState($request, resetPage: true));
    }

    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        $workspace = $this->workspaceContext->forContentRequest($request);
        $link = $workspace->links()->findOrFail($link->getKey());
        app(AdminContentAccess::class)->authorize($request, 'update', $link);

        $validated = $request->validated();
        $tagIds = $validated['tag_ids'] ?? null;
        $routingRules = $validated['routing_rules'] ?? null;

        DB::transaction(function () use ($request, $link, $workspace, $tagIds, $routingRules): void {
            $lockedLink = $workspace->links()->lockForUpdate()->findOrFail($link->id);
            $this->revisions->update(
                $lockedLink,
                $this->linkAttributes->fromRequest($request, $lockedLink),
                $tagIds,
                $routingRules,
                $request->user(),
            );
        });

        return to_route('links.index', $this->listState($request, resetPage: true));
    }

    public function destroy(Request $request, Link $link): RedirectResponse
    {
        $link = $this->workspaceContext->forContentRequest($request)->links()->findOrFail($link->getKey());
        app(AdminContentAccess::class)->authorize($request, 'delete', $link);
        $link->delete();

        return to_route('links.index', $this->listState($request, resetPage: true));
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $validated = $request->validate([
            'action' => ['required', 'in:archive,restore,activate,deactivate,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:20'],
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('links', 'id')->where('workspace_id', $workspace->id),
            ],
        ]);

        DB::transaction(function () use ($workspace, $validated, $request): void {
            $links = $workspace->links()->whereKey($validated['ids'])->lockForUpdate()->get();
            abort_unless($links->count() === count($validated['ids']), 404);

            foreach ($links as $link) {
                if ($validated['action'] === 'delete') {
                    Gate::authorize('delete', $link);
                    $link->delete();

                    continue;
                }

                Gate::authorize('update', $link);
                $attributes = match ($validated['action']) {
                    'archive' => ['archived_at' => now()],
                    'restore' => ['archived_at' => null],
                    'activate' => ['is_active' => true],
                    'deactivate' => ['is_active' => false],
                };
                $this->revisions->update($link, $attributes, null, null, $request->user());
            }
        });

        return to_route('links.index', $this->listState($request, resetPage: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function listState(Request $request, bool $resetPage = false): array
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('workspace_id', $this->workspaceContext->currentFor($request->user())->id)],
            'tag' => ['nullable', 'integer', Rule::exists('link_tags', 'id')->where('workspace_id', $this->workspaceContext->currentFor($request->user())->id)],
            'status' => ['nullable', 'in:active,inactive,archived,pending'],
            'sort' => ['nullable', 'in:created_at,title,clicks_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:table,cards'],
        ]);

        if ($validator->fails()) {
            return [];
        }

        $state = $validator->validated();
        if ($resetPage) {
            unset($state['page']);
        }

        return $state;
    }
}
