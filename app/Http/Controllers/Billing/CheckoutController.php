<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Billing\PaymentCheckout;
use App\Services\Billing\StripeReadiness;
use App\Services\Settings\PlatformSettings;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentCheckout $checkout,
        private readonly StripeReadiness $readiness,
    ) {}

    public function __invoke(
        Request $request,
        WorkspaceContext $context,
        Plan $plan,
        string $interval,
    ): Response {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);

        $subscriptions = app(PlatformSettings::class)->resolved()['subscriptions'] ?? [];

        if (! (bool) ($subscriptions['billing_enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'billing' => 'Billing is disabled by the platform administrator.',
            ]);
        }

        abort_unless($plan->code !== 'free' && $plan->is_active, 404);
        abort_unless(in_array($interval, ['month', 'year'], true), 404);

        $priceId = $this->readiness->priceId($plan, $interval);

        if (! $this->readiness->checkoutReady() || $priceId === null) {
            throw ValidationException::withMessages([
                'billing' => 'Stripe checkout is not configured for this plan. Review the billing setup checklist.',
            ]);
        }

        $url = $this->checkout->startStripe($workspace, $plan, $interval, $priceId, [
            'success_url' => route('billing.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.checkout.cancel'),
            'allow_promotion_codes' => (bool) config('linkpilot.billing.promotion_codes'),
            'billing_address_collection' => ($subscriptions['billing_address_collection'] ?? 'auto') === 'required' ? 'required' : 'auto',
            'client_reference_id' => (string) $workspace->getKey(),
            'subscription_data' => [
                'metadata' => ['workspace_id' => (string) $workspace->getKey()],
            ],
        ]);

        return Inertia::location($url);
    }
}
