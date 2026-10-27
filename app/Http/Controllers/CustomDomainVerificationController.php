<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use App\Services\Domains\DomainVerificationService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomDomainVerificationController extends Controller
{
    public function __construct(
        private readonly DomainVerificationService $verification,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function verify(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        $this->authorizeIntegrationManagement($request, $customDomain);
        $this->verification->verify($customDomain);

        return to_route('custom-domains.index');
    }

    public function reissue(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        $this->authorizeIntegrationManagement($request, $customDomain);
        $this->verification->reissueToken($customDomain);

        return to_route('custom-domains.index');
    }

    private function authorizeIntegrationManagement(Request $request, CustomDomain $customDomain): void
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        abort_unless((int) $customDomain->workspace_id === (int) $workspace->getKey(), 404);
        Gate::authorize('manageIntegrations', $workspace);
    }
}
