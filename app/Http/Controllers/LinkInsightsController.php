<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScopedAdminContent;
use App\Models\Link;
use App\Services\Admin\AdminContentNavigation;
use App\Services\Admin\AdminLinkEditorData;
use App\Services\Analytics\AnalyticsInsights;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LinkInsightsController extends Controller
{
    public function __construct(
        private readonly AnalyticsInsights $analytics,
        private readonly WorkspaceContext $workspaceContext,
        private readonly AdminContentNavigation $navigation,
        private readonly AdminLinkEditorData $linkEditor,
    ) {}

    public function __invoke(Request $request, Link $link): Response
    {
        app(AdminContentAccess::class)->authorize($request, 'view', $link);
        $workspace = $this->workspaceContext->forContentRequest($request);
        abort_unless((int) $link->workspace_id === (int) $workspace->getKey(), 404);
        /** @var Link $link */
        $link = $workspace->links()->findOrFail($link->getKey());

        $days = $this->selectedDays($request);
        $admin = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);

        if (is_array($admin)) {
            $data = $this->linkEditor->load($workspace, $link);
            $link = $data['link'];

            return Inertia::render('admin/content/link-insights', [
                'link' => $link,
                'analytics' => $this->analytics->forLink($link, $days),
                'backUrl' => $this->navigation->withListQuery($request, $admin['list_url']),
                'backLabel' => 'Short links',
                'edit' => [
                    'folders' => $data['folders'],
                    'customDomains' => $data['customDomains'],
                    'linkOverlays' => $data['linkOverlays'],
                    'linkPages' => $data['linkPages'],
                    'tagOptions' => $data['tagOptions'],
                    'countries' => $data['countries'],
                    'canManageApprovals' => $data['canManageApprovals'],
                    'listQuery' => http_build_query([
                        ...$this->navigation->listQuery($request),
                        'days' => (string) $days,
                    ]),
                ],
                'windowBaseUrl' => $this->navigation->withListQuery(
                    $request,
                    route('admin.content.links.insights', $link, false),
                ),
            ]);
        }

        $link->load(
            'folder:id,name',
            'customDomain:id,hostname',
            'linkOverlay:id,name',
            'linkPage:id,name,slug',
            'tags:id,name,slug,color',
            'routingRules',
        )->loadCount('clicks');

        return Inertia::render('links/insights', [
            'link' => $link,
            'analytics' => $this->analytics->forLink($link, $days),
        ]);
    }

    private function selectedDays(Request $request): int
    {
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT);

        abort_unless(is_int($days) && in_array($days, [7, 30, 90], true), 422);

        return $days;
    }
}
