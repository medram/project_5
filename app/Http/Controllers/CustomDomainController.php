<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreCustomDomainRequest;
use App\Models\CustomDomain;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Platform\AdminContentAccess;
use App\Services\Settings\PlatformSettings;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomDomainController extends Controller
{
    public function __construct(
        private readonly EnforcesPlanLimits $planLimits,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());

        $canManageIntegrations = Gate::allows('manageIntegrations', $workspace);
        $domains = $workspace->customDomains()
            ->when(! $canManageIntegrations, fn ($query) => $query->where('status', 'active'))
            ->withCount('links')
            ->latest()
            ->get()
            ->map(function (CustomDomain $domain) use ($canManageIntegrations): array {
                $data = [
                    'id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'status' => $domain->status,
                    'verifiedAt' => $domain->verified_at?->toISOString(),
                    'lastCheckedAt' => $domain->last_checked_at?->toISOString(),
                    'linksCount' => $domain->links_count,
                ];

                if (! $canManageIntegrations) {
                    return $data;
                }

                return [
                    ...$data,
                    'verificationToken' => $domain->verification_token,
                    'txtHost' => "_linkpilot-verify.{$domain->hostname}",
                    'txtValue' => "linkpilot={$domain->verification_token}",
                    'challengeUrl' => "https://{$domain->hostname}/.well-known/linkpilot-domain/{$domain->verification_token}",
                    'failureReason' => $domain->failure_reason,
                ];
            })
            ->all();

        return Inertia::render('custom-domains/index', [
            'domains' => $domains,
            'canManageIntegrations' => $canManageIntegrations,
        ]);
    }

    public function store(StoreCustomDomainRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);
        abort_unless((bool) (app(PlatformSettings::class)->resolved()['links']['domains_enabled'] ?? true), 403);

        $this->planLimits->create(
            $workspace,
            PlanMetric::Domains,
            fn (Workspace $workspace): CustomDomain => $workspace->customDomains()->create([
                'user_id' => $request->user()->id,
                'hostname' => $request->validated('hostname'),
                'verification_token' => CustomDomain::generateVerificationToken(),
                'status' => 'pending_dns',
            ]),
        );

        return to_route('custom-domains.index');
    }

    public function destroy(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        $workspace = $this->workspaceContext->forContentRequest($request);
        abort_unless((int) $customDomain->workspace_id === (int) $workspace->getKey(), 404);
        app(AdminContentAccess::class)->authorize($request, 'delete', $customDomain);

        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$customDomain->hostname])],
        ]);

        $customDomain->delete();

        return to_route('custom-domains.index');
    }
}
