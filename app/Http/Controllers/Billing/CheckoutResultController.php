<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\PayPalSubscription;
use App\Services\Billing\PlanCatalogPresenter;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutResultController extends Controller
{
    public function __construct(
        private readonly WorkspaceUsage $usage,
        private readonly PlanCatalogPresenter $plans,
    ) {}

    public function success(Request $request, WorkspaceContext $context): Response
    {
        return $this->result($request, $context, 'stripe', false);
    }

    public function cancel(Request $request, WorkspaceContext $context): Response
    {
        return $this->result($request, $context, 'stripe', true);
    }

    public function paypalSuccess(Request $request, WorkspaceContext $context): Response
    {
        return $this->result($request, $context, 'paypal', false);
    }

    public function paypalCancelled(Request $request, WorkspaceContext $context): Response
    {
        return $this->result($request, $context, 'paypal', true);
    }

    private function result(Request $request, WorkspaceContext $context, string $provider, bool $cancelled): Response
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('viewBilling', $workspace);
        $effective = $this->usage->effectivePlanFor($workspace);
        $subscription = $effective['source'] === $provider ? $effective['subscription'] : null;

        return Inertia::render('billing/checkout-result', [
            'status' => $cancelled ? 'cancelled' : 'success',
            'checkoutProvider' => $provider,
            'processing' => ! $cancelled && $effective['source'] !== $provider,
            'sessionReturned' => $request->filled('session_id') || $request->filled('subscription_id') || $request->filled('token'),
            'effectivePlan' => $this->plans->data($effective['plan']),
            'effectiveSource' => $effective['source'],
            'provider' => $subscription === null ? null : [
                'status' => $subscription instanceof PayPalSubscription ? $subscription->status : $subscription->stripe_status,
                'endsAt' => $subscription instanceof PayPalSubscription ? null : $subscription->ends_at?->toISOString(),
            ],
        ]);
    }
}
