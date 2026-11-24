<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScopedAdminContent;
use App\Models\CustomDomain;
use App\Services\Domains\DomainVerificationService;
use App\Services\Platform\AdminContentAccess;
use App\Services\Platform\AdminContentMutation;
use App\Services\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomDomainVerificationController extends Controller
{
    public function __construct(
        private readonly DomainVerificationService $verification,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function verify(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        $this->authorizeIntegrationManagement($request, $customDomain);
        $scope = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);
        if (is_array($scope)) {
            $this->verification->verify($customDomain, fn (Closure $apply) => app(AdminContentMutation::class)
                ->run($request, $customDomain, 'admin.content.domains.verify', $apply));

            return redirect($scope['editor_url']);
        }

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
        $workspace = $this->workspaceContext->forContentRequest($request);
        abort_unless((int) $customDomain->workspace_id === (int) $workspace->getKey(), 404);
        app(AdminContentAccess::class)->authorize($request, 'manageIntegrations', $workspace);
    }
}
