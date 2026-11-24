<?php

namespace App\Services\Admin;

use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\Workspace;
use App\Services\Accounts\AccountProfileOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class AdminLinkEditorData
{
    public function __construct(
        private readonly AccountProfileOptions $profileOptions,
    ) {}

    /**
     * @return array{
     *     link: Link,
     *     folders: Collection<int, Model>,
     *     tagOptions: Collection<int, Model>,
     *     customDomains: Collection<int, Model>,
     *     linkOverlays: Collection<int, LinkOverlay>,
     *     linkPages: Collection<int, LinkPage>,
     *     countries: array<int, array{code: string, name: string}>,
     *     canManageApprovals: bool
     * }
     */
    public function load(Workspace $workspace, Link $link): array
    {
        $linkId = $link->getKey();
        $customDomainId = $link->custom_domain_id;
        $linkOverlayId = $link->link_overlay_id;
        $linkPageId = $link->link_page_id;
        /** @var Link $link */
        $link = $workspace->links()
            ->with([
                'folder' => fn ($query) => $query->where('workspace_id', $workspace->id)->select(['id', 'name']),
                'tags' => fn ($query) => $query->where('link_tags.workspace_id', $workspace->id)->select(['link_tags.id', 'link_tags.name', 'link_tags.slug', 'link_tags.color']),
                'customDomain' => fn ($query) => $query->where('workspace_id', $workspace->id)->select(['id', 'hostname', 'status']),
                'linkOverlay' => fn ($query) => $query->where('workspace_id', $workspace->id)->select(['id', 'name']),
                'linkPage' => fn ($query) => $query->where('workspace_id', $workspace->id)->select(['id', 'name', 'slug']),
                'routingRules' => fn ($query) => $query->where('workspace_id', $workspace->id),
            ])
            ->withExists(['revisions as has_pending_revision' => fn ($query) => $query
                ->where('workspace_id', $workspace->id)->where('status', 'pending')])
            ->withCount('clicks')
            ->findOrFail($linkId);

        return [
            'link' => $link,
            'folders' => $workspace->folders()->orderBy('name')->get(['id', 'name']),
            'tagOptions' => $workspace->linkTags()->orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'customDomains' => $workspace->customDomains()
                ->where(fn ($query) => $query->where('status', 'active')->orWhere('id', $customDomainId))
                ->orderBy('hostname')->get(['id', 'hostname']),
            'linkOverlays' => $workspace->linkOverlays()
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $linkOverlayId))
                ->orderBy('name')->get(['id', 'name']),
            'linkPages' => $workspace->linkPages()
                ->where(fn ($query) => $query->where('is_published', true)->orWhere('id', $linkPageId))
                ->orderBy('name')->get(['id', 'name', 'slug']),
            'countries' => $this->profileOptions->countries(),
            'canManageApprovals' => true,
        ];
    }
}
