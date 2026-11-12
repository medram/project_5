<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectFreePlanRequest;
use App\Models\Plan;
use App\Services\Billing\PlanCatalogPresenter;
use App\Services\Billing\StripeReadiness;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Subscription;

class BillingController extends Controller
{
    public function __construct(
        private readonly WorkspaceUsage $usage,
        private readonly StripeReadiness $readiness,
        private readonly PlanCatalogPresenter $plans,
    ) {}

    public function index(Request $request, WorkspaceContext $context): Response
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('viewBilling', $workspace);
        $effective = $this->usage->effectivePlanFor($workspace);

        return Inertia::render('billing/index', [
            'effectivePlan' => $this->plans->data($effective['plan']),
            'effectiveSource' => $effective['source'],
            'override' => $effective['override'] === null ? null : [
                'source' => $effective['override']->source,
                'reason' => $effective['override']->reason,
                'expiresAt' => $effective['override']->expires_at?->toISOString(),
            ],
            'providerSubscription' => $this->providerSubscription($effective['subscription']),
            'plans' => $this->plans->all(),
            'canManageBilling' => Gate::allows('manageBilling', $workspace),
            'usage' => $this->usage->for($workspace),
            'billing' => [
                'checkoutReady' => $this->readiness->checkoutReady(),
                'portalReady' => $this->readiness->configured(),
                'missing' => $this->readiness->missing(),
                'promotionCodes' => (bool) config('linkpilot.billing.promotion_codes'),
            ],
        ]);
    }

    public function selectFree(SelectFreePlanRequest $request, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);
        $effective = $this->usage->effectivePlanFor($workspace);

        if ($effective['source'] === 'stripe') {
            throw ValidationException::withMessages([
                'subscription' => 'Manage the active Stripe subscription in the billing portal before changing the local plan.',
            ]);
        }

        $freePlan = Plan::query()->where('code', $request->validated('plan'))->sole();
        $this->usage->assignPlan($workspace, $freePlan);

        return to_route('billing.index');
    }

    /**
     * @return array{status: string, price: string|null, interval: string|null, trialEndsAt: string|null, endsAt: string|null, onGracePeriod: bool}|null
     */
    private function providerSubscription(?Subscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        $prices = collect([$subscription->stripe_price])
            ->merge($subscription->items->pluck('stripe_price'))
            ->filter()
            ->unique();

        $interval = null;

        foreach ($prices as $price) {
            $interval = $this->readiness->intervalForPrice((string) $price);

            if ($interval !== null) {
                break;
            }
        }

        return [
            'status' => $subscription->stripe_status,
            'price' => $subscription->stripe_price,
            'interval' => $interval,
            'trialEndsAt' => $subscription->trial_ends_at?->toISOString(),
            'endsAt' => $subscription->ends_at?->toISOString(),
            'onGracePeriod' => $subscription->ends_at !== null && $subscription->ends_at->isFuture()
                && in_array($subscription->stripe_status, ['active', 'canceled'], true),
        ];
    }
}
