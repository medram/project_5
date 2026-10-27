<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\LinkRevision;
use App\Services\Links\LinkRevisionService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LinkRevisionController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaces,
        private readonly LinkRevisionService $revisions,
    ) {}

    public function index(Request $request, Link $link): Response
    {
        $workspace = $this->workspaces->currentFor($request->user());
        $link = $workspace->links()->findOrFail($link->getKey());
        Gate::authorize('view', $link);

        $revisions = $link->revisions()->where('workspace_id', $workspace->id)->with(['actor:id,name', 'reviewer:id,name'])->get();
        $snapshotIds = static fn (string $key): array => $revisions
            ->map(fn (LinkRevision $revision): mixed => $revision->snapshot[$key] ?? null)
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $folderNames = $workspace->folders()->whereIn('id', $snapshotIds('folder_id'))->pluck('name', 'id');
        $domainNames = $workspace->customDomains()->whereIn('id', $snapshotIds('custom_domain_id'))->pluck('hostname', 'id');
        $overlayNames = $workspace->linkOverlays()->whereIn('id', $snapshotIds('link_overlay_id'))->pluck('name', 'id');
        $pages = $workspace->linkPages()->whereIn('id', $snapshotIds('link_page_id'))->get(['id', 'name', 'slug'])->keyBy('id');
        $tagNames = $workspace->linkTags()->whereIn(
            'id',
            $revisions->flatMap(fn (LinkRevision $revision): array => $revision->snapshot['tag_ids'] ?? [])
                ->filter(fn (mixed $id): bool => is_numeric($id))
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values(),
        )->pluck('name', 'id');

        $history = $revisions->map(function (LinkRevision $revision) use ($folderNames, $domainNames, $overlayNames, $pages, $tagNames): array {
            $snapshot = $revision->snapshot;
            $labels = is_array($snapshot['display_labels'] ?? null) ? $snapshot['display_labels'] : [];
            $storedLabel = static function (string $key, ?string $fallback) use ($labels): ?string {
                if (! array_key_exists($key, $labels)) {
                    return $fallback;
                }

                return is_string($labels[$key]) ? $labels[$key] : null;
            };
            $page = $pages->get((int) ($snapshot['link_page_id'] ?? 0));
            $routingRules = is_array($snapshot['routing_rules'] ?? null)
                ? $snapshot['routing_rules']
                : [];

            return [
                'id' => $revision->id,
                'number' => $revision->revision_number,
                'status' => $revision->status,
                'actor' => $revision->actor?->name ?? 'Deleted user',
                'reviewer' => $revision->reviewer?->name,
                'reviewNote' => $revision->review_note,
                'createdAt' => $revision->created_at?->toIso8601String(),
                'reviewedAt' => $revision->reviewed_at?->toIso8601String(),
                'slug' => $snapshot['slug'] ?? null,
                'title' => $snapshot['title'] ?? null,
                'deliveryMode' => $snapshot['delivery_mode'] ?? 'direct',
                'target' => $snapshot['destination_url'] ?? '',
                'routingRules' => array_values(array_map(
                    static fn (array $rule, int $index): array => [
                        'position' => $index + 1,
                        'condition' => ($rule['rule_type'] ?? 'rule').': '.($rule['match_value'] ?? ''),
                        'destination' => $rule['target_url'] ?? '',
                    ],
                    $routingRules,
                    array_keys($routingRules),
                )),
                'pageLabel' => $storedLabel('link_page', $page === null ? null : $page->name.' · /'.$page->slug),
                'overlayLabel' => $storedLabel('overlay', $overlayNames->get((int) ($snapshot['link_overlay_id'] ?? 0))),
                'customDomainLabel' => $storedLabel('custom_domain', $domainNames->get((int) ($snapshot['custom_domain_id'] ?? 0))),
                'folderLabel' => $storedLabel('folder', $folderNames->get((int) ($snapshot['folder_id'] ?? 0))),
                'tagLabels' => is_array($labels['tags'] ?? null)
                    ? array_values(array_filter($labels['tags'], static fn (mixed $name): bool => is_string($name)))
                    : collect($snapshot['tag_ids'] ?? [])
                        ->map(fn (mixed $id): ?string => is_numeric($id) ? $tagNames->get((int) $id) : null)
                        ->filter(fn (?string $name): bool => $name !== null)
                        ->values(),
                'isActive' => (bool) ($snapshot['is_active'] ?? false),
                'availableFrom' => $snapshot['available_from'] ?? null,
                'expiresAt' => $snapshot['expires_at'] ?? null,
                'maxClicks' => $snapshot['max_clicks'] ?? null,
                'approvalRequired' => (bool) ($snapshot['approval_required'] ?? false),
                'approvalStatus' => $snapshot['approval_status'] ?? null,
                'utmParameters' => $snapshot['utm_parameters'] ?? [],
                'customParameters' => $snapshot['custom_parameters'] ?? [],
                'hasAccessCode' => ! empty($snapshot['access_code']),
            ];
        })->values();

        return Inertia::render('links/revisions', [
            'link' => ['id' => $link->id, 'slug' => $link->slug, 'title' => $link->title],
            'revisions' => $history,
            'canReview' => Gate::allows('manageLinkApprovals', $workspace),
            'listQuery' => http_build_query($this->listState($request, $workspace->id)),
        ]);
    }

    public function approve(Request $request, Link $link, LinkRevision $revision): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        $link = $workspace->links()->findOrFail($link->getKey());
        Gate::authorize('manageLinkApprovals', $workspace);

        try {
            $approved = DB::transaction(function () use ($link, $revision, $request): bool {
                $lockedLink = Link::query()->lockForUpdate()->findOrFail($link->getKey());
                $lockedRevision = $lockedLink->revisions()->where('workspace_id', $lockedLink->workspace_id)->lockForUpdate()->findOrFail($revision->getKey());

                return $this->revisions->approve($lockedLink, $lockedRevision, $request->user());
            });
        } catch (UniqueConstraintViolationException) {
            LinkRevision::query()
                ->where('link_id', $link->getKey())
                ->where('workspace_id', $workspace->id)
                ->whereKey($revision->getKey())
                ->where('status', 'pending')
                ->update([
                    'status' => 'stale',
                    'reviewer_user_id' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);

            abort(409);
        }
        abort_unless($approved, 409);

        return to_route('links.revisions.index', ['link' => $link->id] + $this->listState($request, $workspace->id));
    }

    public function reject(Request $request, Link $link, LinkRevision $revision): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $workspace = $this->workspaces->currentFor($request->user());
        $link = $workspace->links()->findOrFail($link->getKey());
        Gate::authorize('manageLinkApprovals', $workspace);

        $rejected = DB::transaction(function () use ($link, $revision, $request, $validated): bool {
            $lockedLink = Link::query()->lockForUpdate()->findOrFail($link->getKey());
            $lockedRevision = $lockedLink->revisions()->where('workspace_id', $lockedLink->workspace_id)->lockForUpdate()->findOrFail($revision->getKey());

            return $this->revisions->reject($lockedRevision, $request->user(), $validated['note'] ?? null);
        });
        abort_unless($rejected, 409);

        return to_route('links.revisions.index', ['link' => $link->id] + $this->listState($request, $workspace->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function listState(Request $request, int $workspaceId): array
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('workspace_id', $workspaceId)],
            'tag' => ['nullable', 'integer', Rule::exists('link_tags', 'id')->where('workspace_id', $workspaceId)],
            'status' => ['nullable', 'in:active,inactive,archived,pending'],
            'sort' => ['nullable', 'in:created_at,title,clicks_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:table,cards'],
        ]);

        return $validator->fails() ? [] : $validator->validated();
    }
}
