<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScopedAdminContent;
use App\Models\QrCode;
use App\Services\Admin\AdminContentNavigation;
use App\Services\Platform\AdminContentAccess;
use App\Services\QrCodes\QrCodeAnalytics;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeInsightsController extends Controller
{
    public function __construct(
        private readonly QrCodeAnalytics $analytics,
        private readonly WorkspaceContext $workspaceContext,
        private readonly AdminContentNavigation $navigation,
    ) {}

    public function workspace(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());

        return Inertia::render('qr-codes/insights', [
            'scope' => 'workspace',
            'qrCode' => null,
            'analytics' => $this->analytics->forWorkspace($workspace, $this->selectedDays($request)),
        ]);
    }

    public function campaign(Request $request, QrCode $qrCode): Response
    {
        $admin = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);

        if (is_array($admin)) {
            app(AdminContentAccess::class)->authorize($request, 'view', $qrCode);
        }

        $workspace = $this->workspaceContext->forContentRequest($request);
        /** @var QrCode $qrCode */
        $qrCode = $workspace->qrCodes()
            ->with(['link' => fn ($query) => $query
                ->where('links.workspace_id', $workspace->id)
                ->select(['links.id', 'links.slug', 'links.title'])])
            ->findOrFail($qrCode->getKey());

        $props = [
            'scope' => 'campaign',
            'qrCode' => $qrCode,
            'analytics' => $this->analytics->forCampaign($qrCode, $this->selectedDays($request)),
        ];

        if (is_array($admin)) {
            $props['backUrl'] = $this->navigation->withListQuery($request, $admin['list_url']);
            $props['backLabel'] = 'QR codes';
            $props['editUrl'] = $this->navigation->withListQuery(
                $request,
                route('admin.content.qr-codes.edit', $qrCode, false),
            );
            $props['windowBaseUrl'] = $this->navigation->withListQuery(
                $request,
                route('admin.content.qr-codes.insights', $qrCode, false),
            );

            return Inertia::render('admin/content/qr-insights', $props);
        }

        return Inertia::render('qr-codes/insights', $props);
    }

    private function selectedDays(Request $request): int
    {
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT);

        abort_unless(is_int($days) && in_array($days, [7, 30, 90], true), 422);

        return $days;
    }
}
