<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\Billing\StripeReadiness;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Settings\PlatformSettings;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly WorkspaceUsage $usage,
        private readonly StripeReadiness $readiness,
    ) {}

    public function __invoke(
        Request $request,
        WorkspaceContext $context,
        Plan $plan,
        string $interval,
    ): RedirectResponse {
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

        if ($this->usage->effectivePlanFor($workspace)['source'] === 'stripe') {
            throw ValidationException::withMessages([
                'subscription' => 'Manage the active Stripe subscription in the billing portal before starting another checkout.',
            ]);
        }

        $checkout = $workspace->newSubscription('default', $priceId)->checkout([
            'success_url' => route('billing.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.checkout.cancel'),
            'allow_promotion_codes' => (bool) config('linkpilot.billing.promotion_codes'),
            'billing_address_collection' => ($subscriptions['billing_address_collection'] ?? 'auto') === 'required' ? 'required' : 'auto',
            'client_reference_id' => (string) $workspace->getKey(),
            'subscription_data' => [
                'metadata' => ['workspace_id' => (string) $workspace->getKey()],
            ],
        ]);

        return $checkout->redirect();
    }
}
