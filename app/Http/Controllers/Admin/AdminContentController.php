<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminContentRequest;
use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\TrackingPixel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentController extends Controller
{
    public function links(AdminContentRequest $request): Response
    {
        $query = Link::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('clicks');

        $this->search($query, $request->search(), 'title', 'slug');
        $this->filterLinkState($query, $request->state());

        return $this->render(
            request: $request,
            key: 'links',
            title: 'Short links',
            description: 'Cross-workspace short-link metadata. Destinations, access rules, and parameter values are not exposed.',
            identifierLabel: 'Slug',
            nameColumn: 'title',
            stateOptions: [['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive'], ['value' => 'archived', 'label' => 'Archived']],
            query: $query,
            map: fn (Link $link): array => $this->row(
                $link,
                name: $link->title ?: $link->slug,
                identifier: $link->slug,
                state: $link->archived_at !== null ? 'Archived' : ($link->is_active ? 'Active' : 'Inactive'),
                type: 'Short link',
                counts: [['label' => 'Clicks', 'value' => (int) $link->clicks_count]],
            ),
        );
    }

    public function bioLinks(AdminContentRequest $request): Response
    {
        $query = BioPage::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount(['bioLinks', 'contentBlocks']);

        $this->search($query, $request->search(), 'display_name', 'handle');
        $this->filterPublished($query, $request->state());

        return $this->render(
            request: $request,
            key: 'biolinks',
            title: 'Link in bio pages',
            description: 'Cross-workspace bio-page metadata. Bio text, links, and visitor data are not exposed.',
            identifierLabel: 'Handle',
            nameColumn: 'display_name',
            stateOptions: [['value' => 'published', 'label' => 'Published'], ['value' => 'draft', 'label' => 'Draft']],
            query: $query,
            map: fn (BioPage $page): array => $this->row(
                $page,
                name: $page->display_name,
                identifier: '@'.$page->handle,
                state: $page->is_published ? 'Published' : 'Draft',
                type: 'Bio page',
                counts: [
                    ['label' => 'Bio links', 'value' => (int) $page->bio_links_count],
                    ['label' => 'Blocks', 'value' => (int) $page->content_blocks_count],
                ],
            ),
        );
    }

    public function folders(AdminContentRequest $request): Response
    {
        $query = Folder::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('links');

        $this->search($query, $request->search(), 'name');
        $this->rejectUnknownState($query, $request->state(), ['active']);

        return $this->render(
            request: $request,
            key: 'folders',
            title: 'Folders',
            description: 'Cross-workspace folder metadata and link counts. Folder contents are not exposed.',
            identifierLabel: null,
            nameColumn: 'name',
            stateOptions: [['value' => 'active', 'label' => 'Active']],
            query: $query,
            map: fn (Folder $folder): array => $this->row(
                $folder,
                name: $folder->name,
                identifier: null,
                state: 'Active',
                type: 'Folder',
                counts: [['label' => 'Links', 'value' => (int) $folder->links_count]],
            ),
        );
    }

    public function domains(AdminContentRequest $request): Response
    {
        $query = CustomDomain::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('links');

        $this->search($query, $request->search(), 'hostname');
        if ($request->state() !== null) {
            $query->where('status', $request->state());
        }

        return $this->render(
            request: $request,
            key: 'custom-domains',
            title: 'Custom domains',
            description: 'Cross-workspace hostname metadata. Verification tokens, failure bodies, and DNS details are not exposed.',
            identifierLabel: 'Hostname',
            nameColumn: 'hostname',
            stateOptions: [
                ['value' => 'pending_dns', 'label' => 'Pending DNS'],
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'failed', 'label' => 'Failed'],
            ],
            query: $query,
            map: fn (CustomDomain $domain): array => $this->row(
                $domain,
                name: $domain->hostname,
                identifier: $domain->hostname,
                state: (string) $domain->status,
                type: 'Custom domain',
                counts: [['label' => 'Links', 'value' => (int) $domain->links_count]],
                updatedAt: $domain->verified_at ?? $domain->updated_at,
            ),
        );
    }

    public function overlays(AdminContentRequest $request): Response
    {
        $query = LinkOverlay::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('links');

        $this->search($query, $request->search(), 'name');
        $this->filterActive($query, $request->state());

        return $this->render(
            request: $request,
            key: 'link-overlays',
            title: 'CTA overlays',
            description: 'Cross-workspace overlay metadata and availability. Overlay copy and targeting rules are not exposed.',
            identifierLabel: null,
            nameColumn: 'name',
            stateOptions: [['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive']],
            query: $query,
            map: fn (LinkOverlay $overlay): array => $this->row(
                $overlay,
                name: $overlay->name,
                identifier: null,
                state: $overlay->is_active ? 'Active' : 'Inactive',
                type: 'CTA overlay',
                counts: [['label' => 'Linked links', 'value' => (int) $overlay->links_count]],
                extra: [
                    'Starts' => $overlay->starts_at?->toISOString(),
                    'Expires' => $overlay->expires_at?->toISOString(),
                ],
            ),
        );
    }

    public function pages(AdminContentRequest $request): Response
    {
        $query = LinkPage::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount(['items', 'blocks']);

        $this->search($query, $request->search(), 'name', 'slug');
        $this->filterPublished($query, $request->state());

        return $this->render(
            request: $request,
            key: 'link-pages',
            title: 'Link pages',
            description: 'Cross-workspace link-page metadata. Blocks, leads, variants, and destination URLs are not exposed.',
            identifierLabel: 'Slug',
            nameColumn: 'name',
            stateOptions: [['value' => 'published', 'label' => 'Published'], ['value' => 'draft', 'label' => 'Draft']],
            query: $query,
            map: fn (LinkPage $page): array => $this->row(
                $page,
                name: $page->name,
                identifier: $page->slug,
                state: $page->is_published ? 'Published' : 'Draft',
                type: $page->template ?: 'Link page',
                counts: [
                    ['label' => 'Items', 'value' => (int) $page->items_count],
                    ['label' => 'Blocks', 'value' => (int) $page->blocks_count],
                ],
            ),
        );
    }

    public function pixels(AdminContentRequest $request): Response
    {
        $query = TrackingPixel::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('rules');

        $this->search($query, $request->search(), 'name');
        $this->filterActive($query, $request->state());

        return $this->render(
            request: $request,
            key: 'pixels',
            title: 'Tracking pixels',
            description: 'Cross-workspace pixel metadata. Provider IDs, event payloads, and visitor dimensions are not exposed.',
            identifierLabel: null,
            nameColumn: 'name',
            stateOptions: [['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive']],
            query: $query,
            map: fn (TrackingPixel $pixel): array => $this->row(
                $pixel,
                name: $pixel->name,
                identifier: null,
                state: $pixel->is_active ? 'Active' : 'Inactive',
                type: 'Tracking pixel',
                provider: $pixel->provider,
                counts: [['label' => 'Rules', 'value' => (int) $pixel->rules_count]],
            ),
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<array{value: string, label: string}>  $stateOptions
     * @param  callable(TModel): array<string, mixed>  $map
     */
    private function render(
        AdminContentRequest $request,
        string $key,
        string $title,
        string $description,
        ?string $identifierLabel,
        string $nameColumn,
        array $stateOptions,
        Builder $query,
        callable $map,
    ): Response {
        $sort = $request->sort();
        $column = $sort === 'name' ? $nameColumn : $sort;
        $direction = $request->direction() === 'asc' ? 'asc' : 'desc';

        $rows = $query
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through($map);

        return Inertia::render('admin/content/index', [
            'resource' => [
                'key' => $key,
                'title' => $title,
                'description' => $description,
                'identifierLabel' => $identifierLabel,
                'stateOptions' => $stateOptions,
            ],
            'rows' => $rows,
            'filters' => [
                'q' => $request->search(),
                'state' => $request->state(),
                'sort' => $sort,
                'direction' => $request->direction(),
            ],
        ]);
    }

    /**
     * @param  list<array{label: string, value: int}>  $counts
     * @param  array<string, string|null>  $extra
     * @return array<string, mixed>
     */
    private function row(
        Model $model,
        string $name,
        ?string $identifier,
        string $state,
        string $type,
        ?string $provider = null,
        array $counts = [],
        mixed $updatedAt = null,
        array $extra = [],
    ): array {
        $workspace = $model->getRelation('workspace');

        return [
            'name' => $name,
            'identifier' => $identifier,
            'workspace' => $workspace?->getAttribute('name'),
            'owner' => $workspace?->getRelation('owner')?->getAttribute('name'),
            'state' => $state,
            'type' => $type,
            'provider' => $provider,
            'counts' => $counts,
            'createdAt' => $this->iso($model->getAttribute('created_at')),
            'updatedAt' => $this->iso($updatedAt ?? $model->getAttribute('updated_at')),
            'extra' => $extra,
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toISOString();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) ? $value : null;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function search(Builder $query, string $search, string $nameColumn, ?string $identifierColumn = null): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.$search.'%';

        $query->where(function (Builder $matches) use ($like, $nameColumn, $identifierColumn): void {
            $matches->where($nameColumn, 'like', $like);

            if ($identifierColumn !== null) {
                $matches->orWhere($identifierColumn, 'like', $like);
            }

            $matches
                ->orWhereHas('workspace', fn (Builder $workspace) => $workspace
                    ->where('name', 'like', $like)
                    ->orWhereHas('owner', fn (Builder $owner) => $owner->where('name', 'like', $like)));
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function filterLinkState(Builder $query, ?string $state): void
    {
        if ($state === 'active') {
            $query->where('is_active', true)->whereNull('archived_at');
        } elseif ($state === 'inactive') {
            $query->where('is_active', false)->whereNull('archived_at');
        } elseif ($state === 'archived') {
            $query->whereNotNull('archived_at');
        } elseif ($state !== null) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function filterPublished(Builder $query, ?string $state): void
    {
        if ($state === 'published') {
            $query->where('is_published', true);
        } elseif ($state === 'draft') {
            $query->where('is_published', false);
        } elseif ($state !== null) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function filterActive(Builder $query, ?string $state): void
    {
        if ($state === 'active') {
            $query->where('is_active', true);
        } elseif ($state === 'inactive') {
            $query->where('is_active', false);
        } elseif ($state !== null) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $allowed
     */
    private function rejectUnknownState(Builder $query, ?string $state, array $allowed): void
    {
        if ($state !== null && ! in_array($state, $allowed, true)) {
            $query->whereRaw('1 = 0');
        }
    }
}
