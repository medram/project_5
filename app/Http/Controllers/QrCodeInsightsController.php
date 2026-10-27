<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
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
        $workspace = $this->workspaceContext->currentFor($request->user());
        $qrCode = $workspace->qrCodes()
            ->with(['link' => fn ($query) => $query
                ->where('links.workspace_id', $workspace->id)
                ->select(['links.id', 'links.slug', 'links.title'])])
            ->findOrFail($qrCode->getKey());

        return Inertia::render('qr-codes/insights', [
            'scope' => 'campaign',
            'qrCode' => $qrCode,
            'analytics' => $this->analytics->forCampaign($qrCode, $this->selectedDays($request)),
        ]);
    }

    private function selectedDays(Request $request): int
    {
        $days = filter_var($request->query('days', 30), FILTER_VALIDATE_INT);

        abort_unless(is_int($days) && in_array($days, [7, 30, 90], true), 422);

        return $days;
    }
}
