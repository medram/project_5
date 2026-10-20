<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomDomainRequest;
use App\Models\CustomDomain;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomDomainController extends Controller
{
    public function __construct(private readonly EnforcesPlanLimits $planLimits) {}

    public function index(Request $request): Response
    {
        $domains = $request->user()->customDomains()
            ->withCount('links')
            ->latest()
            ->get()
            ->map(fn (CustomDomain $domain): array => [
                'id' => $domain->id,
                'hostname' => $domain->hostname,
                'status' => $domain->status,
                'verificationToken' => $domain->verification_token,
                'txtHost' => "_linkpilot-verify.{$domain->hostname}",
                'txtValue' => "linkpilot={$domain->verification_token}",
                'challengeUrl' => "https://{$domain->hostname}/.well-known/linkpilot-domain/{$domain->verification_token}",
                'verifiedAt' => $domain->verified_at?->toISOString(),
                'lastCheckedAt' => $domain->last_checked_at?->toISOString(),
                'failureReason' => $domain->failure_reason,
                'linksCount' => $domain->links_count,
            ])
            ->all();

        return Inertia::render('custom-domains/index', [
            'domains' => $domains,
        ]);
    }

    public function store(StoreCustomDomainRequest $request): RedirectResponse
    {
        $this->planLimits->create(
            $request->user(),
            'domains',
            fn ($user): CustomDomain => $user->customDomains()->create([
                'hostname' => $request->validated('hostname'),
                'verification_token' => CustomDomain::generateVerificationToken(),
                'status' => 'pending_dns',
            ]),
        );

        return to_route('custom-domains.index');
    }

    public function destroy(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        Gate::authorize('delete', $customDomain);

        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$customDomain->hostname])],
        ]);

        $customDomain->delete();

        return to_route('custom-domains.index');
    }
}
