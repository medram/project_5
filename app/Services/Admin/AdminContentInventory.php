<?php

namespace App\Services\Admin;

use App\Enums\PlatformPermission;
use App\Http\Requests\Admin\AdminContentRequest;
use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\QrCode;
use App\Models\TrackingPixel;
use App\Services\Platform\PlatformAuthorization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AdminContentInventory
{
    public function __construct(private readonly PlatformAuthorization $authorization) {}

    /**
     * @return array<string, mixed>
     */
    public function props(AdminContentRequest $request, string $key): array
    {
        return match ($key) {
            'links' => $this->links($request),
            'biolinks' => $this->bioLinks($request),
            'qr-codes' => $this->qrCodes($request),
            'folders' => $this->folders($request),
            'custom-domains' => $this->domains($request),
            'link-overlays' => $this->overlays($request),
            'link-pages' => $this->pages($request),
            'pixels' => $this->pixels($request),
            default => throw new InvalidArgumentException("Unknown admin content inventory [{$key}]."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function links(AdminContentRequest $request): array
    {
        $query = Link::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('clicks');

        $this->search($query, $request->search(), 'title', 'slug');
        $this->filterLinkState($query, $request->state());

        return $this->page(
            request: $request,
            key: 'links',
            title: 'Short links',
            description: 'Cross-workspace short-link metadata. Editors can open item insights and destinations; access codes stay hidden.',
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

    /**
     * @return array<string, mixed>
     */
    private function bioLinks(AdminContentRequest $request): array
    {
        $query = BioPage::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount(['bioLinks', 'contentBlocks']);

        $this->search($query, $request->search(), 'display_name', 'handle');
        $this->filterPublished($query, $request->state());

        return $this->page(
            request: $request,
            key: 'biolinks',
            title: 'Link in bio pages',
            description: 'Cross-workspace bio-page metadata. Editors can open the bio editor and consented analytics; raw visitor identities stay hidden.',
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

    /**
     * @return array<string, mixed>
     */
    private function qrCodes(AdminContentRequest $request): array
    {
        $query = QrCode::query()
            ->select(['id', 'workspace_id', 'code', 'source', 'title', 'is_active', 'scan_count', 'created_at', 'updated_at'])
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name']);

        $this->search($query, $request->search(), 'title', 'code');
        $this->filterActive($query, $request->state());

        return $this->page(
            request: $request,
            key: 'qr-codes',
            title: 'QR codes',
            description: 'Cross-workspace QR metadata. Editors can open campaign insights and destinations; access codes stay hidden.',
            identifierLabel: 'Code',
            nameColumn: 'title',
            stateOptions: [['value' => 'active', 'label' => 'Active'], ['value' => 'inactive', 'label' => 'Inactive']],
            query: $query,
            map: fn (QrCode $qrCode): array => $this->row(
                $qrCode,
                name: $qrCode->title ?: $qrCode->code,
                identifier: $qrCode->code,
                state: $qrCode->is_active ? 'Active' : 'Inactive',
                type: $qrCode->source === 'linked' ? 'Linked QR' : 'QR campaign',
                counts: [['label' => 'Scans', 'value' => (int) $qrCode->scan_count]],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function folders(AdminContentRequest $request): array
    {
        $query = Folder::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('links');

        $this->search($query, $request->search(), 'name');
        $this->rejectUnknownState($query, $request->state(), ['active']);

        return $this->page(
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

    /**
     * @return array<string, mixed>
     */
    private function domains(AdminContentRequest $request): array
    {
        $query = CustomDomain::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('links');

        $this->search($query, $request->search(), 'hostname');
        if ($request->state() !== null) {
            $query->where('status', $request->state());
        }

        return $this->page(
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

    /**
     * @return array<string, mixed>
     */
    private function overlays(AdminContentRequest $request): array
    {
        $query = LinkOverlay::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('links');

        $this->search($query, $request->search(), 'name');
        $this->filterActive($query, $request->state());

        return $this->page(
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

    /**
     * @return array<string, mixed>
     */
    private function pages(AdminContentRequest $request): array
    {
        $query = LinkPage::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount(['items', 'blocks']);

        $this->search($query, $request->search(), 'name', 'slug');
        $this->filterPublished($query, $request->state());

        return $this->page(
            request: $request,
            key: 'link-pages',
            title: 'Link pages',
            description: 'Cross-workspace link-page metadata. Editors can open the page editor and variant insights; lead responses stay owner-only.',
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

    /**
     * @return array<string, mixed>
     */
    private function pixels(AdminContentRequest $request): array
    {
        $query = TrackingPixel::query()
            ->with(['workspace:id,name,owner_id', 'workspace.owner:id,name'])
            ->withCount('rules');

        $this->search($query, $request->search(), 'name');
        $this->filterActive($query, $request->state());

        return $this->page(
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
     * @return array<string, mixed>
     */
    private function page(
        AdminContentRequest $request,
        string $key,
        string $title,
        string $description,
        ?string $identifierLabel,
        string $nameColumn,
        array $stateOptions,
        Builder $query,
        callable $map,
    ): array {
        $sort = $request->sort();
        $column = $sort === 'name' ? $nameColumn : $sort;
        $direction = $request->direction() === 'asc' ? 'asc' : 'desc';
        [$managePermission, $deletePermission, $editRoute, $destroyRoute, $viewRoute] = match ($key) {
            'links' => [PlatformPermission::AdminLinksManage, PlatformPermission::AdminLinksDelete, 'admin.content.links.edit', 'admin.content.links.destroy', 'admin.content.links.insights'],
            'biolinks' => [PlatformPermission::AdminBioPagesManage, PlatformPermission::AdminBioPagesDelete, 'admin.content.biolinks.edit', 'admin.content.biolinks.destroy', 'admin.content.biolinks.insights'],
            'qr-codes' => [PlatformPermission::AdminQrCodesManage, PlatformPermission::AdminQrCodesDelete, 'admin.content.qr-codes.edit', 'admin.content.qr-codes.destroy', 'admin.content.qr-codes.insights'],
            'folders' => [PlatformPermission::AdminFoldersManage, PlatformPermission::AdminFoldersDelete, 'admin.content.folders.edit', 'admin.content.folders.destroy', null],
            'custom-domains' => [PlatformPermission::AdminDomainsManage, PlatformPermission::AdminDomainsDelete, 'admin.content.domains.edit', 'admin.content.domains.destroy', null],
            'link-overlays' => [PlatformPermission::AdminOverlaysManage, PlatformPermission::AdminOverlaysDelete, 'admin.content.overlays.edit', 'admin.content.overlays.destroy', null],
            'link-pages' => [PlatformPermission::AdminLinkPagesManage, PlatformPermission::AdminLinkPagesDelete, 'admin.content.link-pages.edit', 'admin.content.link-pages.destroy', 'admin.content.link-pages.insights'],
            'pixels' => [PlatformPermission::AdminPixelsManage, PlatformPermission::AdminPixelsDelete, 'admin.content.pixels.edit', 'admin.content.pixels.destroy', null],
            default => throw new InvalidArgumentException("Unknown admin content inventory [{$key}]."),
        };
        $canEdit = $this->authorization->allows($request->user(), $managePermission);
        $canDelete = $this->authorization->allows($request->user(), $deletePermission);

        $rows = $query
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Model $model) use ($map, $canEdit, $canDelete, $editRoute, $destroyRoute, $viewRoute): array {
                $row = $map($model);
                if ($canEdit) {
                    $row['editUrl'] = route($editRoute, $model, false);
                    if ($viewRoute !== null) {
                        $row['viewUrl'] = route($viewRoute, $model, false);
                    }
                }
                if ($canDelete) {
                    $row['deleteUrl'] = route($destroyRoute, $model, false);
                    if ($model instanceof CustomDomain) {
                        $row['deleteConfirmation'] = $model->hostname;
                    }
                }

                return $row;
            });

        return [
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
            'editing' => null,
        ];
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
        if ($value instanceof CarbonInterface) {
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
