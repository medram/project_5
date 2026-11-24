<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ScopedAdminContent;
use App\Http\Requests\Admin\AdminContentRequest;
use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\QrCode;
use App\Models\TrackingPixel;
use App\Models\TrackingPixelRule;
use App\Services\Admin\AdminContentInventory;
use App\Services\Admin\AdminContentNavigation;
use App\Services\Admin\AdminLinkEditorData;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentEditController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaces,
        private readonly AdminContentAccess $access,
        private readonly AdminLinkEditorData $linkEditor,
        private readonly AdminContentInventory $inventory,
        private readonly AdminContentNavigation $navigation,
    ) {}

    public function link(AdminContentRequest $request, Link $link): Response
    {
        $workspace = $this->workspaces->forContentRequest($request);
        $this->access->authorize($request, 'view', $link);
        $data = $this->linkEditor->load($workspace, $link);

        return $this->render($request, 'links', $data['link'], [
            'folders' => $data['folders'],
            'tagOptions' => $data['tagOptions'],
            'customDomains' => $data['customDomains'],
            'linkOverlays' => $data['linkOverlays'],
            'linkPages' => $data['linkPages'],
            'countries' => $data['countries'],
            'canManageApprovals' => $data['canManageApprovals'],
        ]);
    }

    public function qrCode(AdminContentRequest $request, QrCode $qrCode): Response
    {
        $workspace = $this->workspaces->forContentRequest($request);
        $this->access->authorize($request, 'view', $qrCode);
        $qrCode = $workspace->qrCodes()
            ->with(['link' => fn ($query) => $query->where('links.workspace_id', $workspace->id)->select(['links.id', 'links.slug', 'links.title'])])
            ->withCount('scans')
            ->findOrFail($qrCode->getKey());

        return $this->render($request, 'qr-codes', $qrCode, [
            'availableLinks' => $workspace->links()->orderBy('title')->get(['id', 'slug', 'title']),
        ]);
    }

    public function folder(AdminContentRequest $request, Folder $folder): Response
    {
        $this->access->authorize($request, 'view', $folder);
        $folder->loadCount(['links', 'clicks', 'analyticsEvents']);

        return $this->render($request, 'folders', $folder);
    }

    public function domain(AdminContentRequest $request, CustomDomain $customDomain): Response
    {
        $this->access->authorize($request, 'view', $customDomain);
        $customDomain->loadCount('links');
        $token = $customDomain->verification_token;

        return $this->render($request, 'custom-domains', [
            'id' => $customDomain->id,
            'hostname' => $customDomain->hostname,
            'status' => $customDomain->status,
            'verificationToken' => $token,
            'txtHost' => "_linkpilot-verify.{$customDomain->hostname}",
            'txtValue' => "linkpilot={$token}",
            'challengeUrl' => "https://{$customDomain->hostname}/.well-known/linkpilot-domain/{$token}",
            'verifiedAt' => $customDomain->verified_at?->toISOString(),
            'lastCheckedAt' => $customDomain->last_checked_at?->toISOString(),
            'failureReason' => $customDomain->failure_reason,
            'linksCount' => $customDomain->links_count,
        ]);
    }

    public function overlay(AdminContentRequest $request, LinkOverlay $linkOverlay): Response
    {
        $this->access->authorize($request, 'view', $linkOverlay);
        $linkOverlay->loadCount('links');

        return $this->render($request, 'link-overlays', [
            'id' => $linkOverlay->id,
            'name' => $linkOverlay->name,
            'heading' => $linkOverlay->heading,
            'body' => $linkOverlay->body,
            'ctaLabel' => $linkOverlay->cta_label,
            'isActive' => $linkOverlay->is_active,
            'startsAt' => $linkOverlay->starts_at?->toISOString(),
            'expiresAt' => $linkOverlay->expires_at?->toISOString(),
            'displayDelaySeconds' => (int) $linkOverlay->display_delay_seconds,
            'linksCount' => $linkOverlay->links_count,
        ]);
    }

    public function pixel(AdminContentRequest $request, TrackingPixel $trackingPixel): Response
    {
        $workspace = $this->workspaces->forContentRequest($request);
        $this->access->authorize($request, 'view', $trackingPixel);
        $trackingPixel->load(['rules' => fn ($query) => $query->where('workspace_id', $workspace->id)]);
        $targets = [
            ...$workspace->bioPages()->orderBy('display_name')->get(['id', 'display_name'])
                ->map(fn (BioPage $page) => ['type' => TrackingPixelRule::TARGET_BIO_PAGE, 'id' => $page->id, 'name' => $page->display_name])->all(),
            ...$workspace->linkPages()->orderBy('name')->get(['id', 'name'])
                ->map(fn (LinkPage $page) => ['type' => TrackingPixelRule::TARGET_LINK_PAGE, 'id' => $page->id, 'name' => $page->name])->all(),
            ...$workspace->linkOverlays()->orderBy('name')->get(['id', 'name'])
                ->map(fn (LinkOverlay $overlay) => ['type' => TrackingPixelRule::TARGET_LINK_OVERLAY, 'id' => $overlay->id, 'name' => $overlay->name])->all(),
        ];

        return $this->render($request, 'pixels', [
            'id' => $trackingPixel->id,
            'name' => $trackingPixel->name,
            'provider' => $trackingPixel->provider,
            'providerId' => $trackingPixel->provider_id,
            'isActive' => $trackingPixel->is_active,
            'rules' => $trackingPixel->rules->map(fn (TrackingPixelRule $rule) => [
                'id' => $rule->id, 'targetType' => $rule->target_type, 'targetId' => $rule->target_id, 'trigger' => $rule->trigger,
            ])->values()->all(),
        ], ['availableTargets' => $targets]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function render(AdminContentRequest $request, string $resource, mixed $record, array $extra = []): Response
    {
        $scope = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);
        $backUrl = is_array($scope)
            ? $this->navigation->withListQuery($request, $scope['list_url'])
            : '/admin';

        return Inertia::render('admin/content/index', [
            ...$this->inventory->props($request, $resource),
            'editing' => [
                'resource' => $resource,
                'record' => $record,
                'backUrl' => $backUrl,
                ...$extra,
            ],
        ]);
    }
}
