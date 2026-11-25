<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Billing\PaymentCheckout;
use App\Services\Billing\PayPalReadiness;
use App\Services\Billing\PayPalSubscriptions;
use App\Services\Settings\PlatformSettings;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PayPalCheckoutController extends Controller
{
    public function checkout(Request $request, WorkspaceContext $context, Plan $plan, string $interval, PayPalReadiness $readiness, PaymentCheckout $checkout): Response
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);

        if (! (bool) (app(PlatformSettings::class)->resolved()['subscriptions']['billing_enabled'] ?? false)) {
            throw ValidationException::withMessages(['billing' => 'Billing is disabled by the platform administrator.']);
        }
        abort_unless($plan->code !== 'free' && $plan->is_active && in_array($interval, ['month', 'year'], true), 404);

        $price = $readiness->price($plan, $interval);
        if (! $readiness->checkoutReady() || $price === null) {
            throw ValidationException::withMessages(['billing' => 'PayPal checkout is not configured for this plan.']);
        }

        return Inertia::location($checkout->startPayPal($workspace, $plan, $price, $interval));
    }

    public function refresh(Request $request, WorkspaceContext $context, PayPalSubscriptions $subscriptions): RedirectResponse
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);
        $subscriptions->refresh($workspace);

        return to_route('billing.index');
    }

    public function cancel(Request $request, WorkspaceContext $context, PayPalSubscriptions $subscriptions): RedirectResponse
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);
        $subscriptions->cancel($workspace);

        return to_route('billing.index');
    }
}
