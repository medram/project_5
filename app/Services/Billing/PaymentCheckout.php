<?php

namespace App\Services\Billing;

use App\Models\PaymentCheckoutAttempt;
use App\Models\PayPalSubscription;
use App\Models\Plan;
use App\Models\PlanPayPalPrice;
use App\Models\Workspace;
use App\Services\Settings\PlatformSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PaymentCheckout
{
    public function __construct(
        private readonly PayPalClient $paypal,
        private readonly PayPalPlanVerifier $paypalPlans,
        private readonly PayPalReadiness $paypalReadiness,
        private readonly StripeReadiness $stripeReadiness,
    ) {}

    public function hasSubscription(Workspace $workspace): bool
    {
        return $this->hasStripeSubscription($workspace)
            || PayPalSubscription::query()->where('workspace_id', $workspace->id)
                ->whereIn('status', ['ACTIVE', 'SUSPENDED', 'APPROVED', 'PAYMENT_FAILED'])->exists();
    }

    public function pending(Workspace $workspace): ?PaymentCheckoutAttempt
    {
        $attempt = PaymentCheckoutAttempt::query()->where('workspace_id', $workspace->id)->first();
        if ($attempt !== null && $attempt->provider === 'stripe' && $this->hasStripeSubscription($workspace)) {
            $attempt->delete();

            return null;
        }

        return $attempt;
    }

    public function startPayPal(Workspace $workspace, Plan $plan, PlanPayPalPrice $price, string $interval): string
    {
        try {
            return DB::transaction(function () use ($workspace, $plan, $price, $interval): string {
                DB::table('platform_setting_sections')->where('section', 'subscriptions')->lockForUpdate()->first();
                $settings = app(PlatformSettings::class);
                $settings->applyRuntime();
                if (! (bool) ($settings->resolved()['subscriptions']['billing_enabled'] ?? false)
                    || ! $this->paypalReadiness->checkoutReady()) {
                    throw ValidationException::withMessages(['billing' => 'PayPal checkout is not configured.']);
                }
                $locked = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
                $this->assertAvailable($locked);
                $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
                $currentPrice = $this->paypalReadiness->price($plan, $interval);
                if ($currentPrice === null || $currentPrice->id !== $price->id) {
                    throw ValidationException::withMessages(['billing' => 'This plan has changed. Refresh billing and choose it again.']);
                }
                $price = $currentPrice;
                try {
                    $amount = $this->paypalPlans->amount($price->paypal_plan_id, $plan, $interval);
                } catch (ValidationException) {
                    throw ValidationException::withMessages(['billing' => 'The PayPal plan could not be verified. Please contact the platform administrator.']);
                }
                if ($amount !== $price->unit_amount) {
                    throw ValidationException::withMessages(['billing' => 'The PayPal price has changed. Please contact the platform administrator.']);
                }
                $this->assertPayPalWebhook();
                $reference = (string) Str::uuid();
                $subscription = PayPalSubscription::query()->create([
                    'workspace_id' => $locked->id,
                    'plan_paypal_price_id' => $price->id,
                    'custom_id' => $reference,
                    'status' => 'CREATING',
                ]);
                $result = $this->paypal->createSubscription(
                    $price->paypal_plan_id,
                    $reference,
                    rtrim((string) config('app.url'), '/').route('billing.paypal.success', [], false),
                    rtrim((string) config('app.url'), '/').route('billing.paypal.cancelled', [], false),
                    $reference,
                );
                $id = $result['id'] ?? null;
                $approval = null;
                foreach (is_array($result['links'] ?? null) ? $result['links'] : [] as $link) {
                    if (is_array($link) && ($link['rel'] ?? null) === 'approve') {
                        $approval = $link;
                        break;
                    }
                }
                $url = $approval['href'] ?? null;
                if (! is_string($id) || $id === '' || ! is_string($url) || ! $this->isPayPalUrl($url)
                    || ! in_array($result['status'] ?? null, ['APPROVAL_PENDING', 'APPROVED'], true)) {
                    throw new \RuntimeException('Invalid PayPal checkout response.');
                }
                $subscription->update(['paypal_subscription_id' => $id, 'status' => $result['status']]);
                PaymentCheckoutAttempt::query()->create([
                    'workspace_id' => $locked->id, 'plan_id' => $plan->id, 'provider' => 'paypal',
                    'interval' => $interval, 'reference' => $reference, 'provider_id' => $id, 'approval_url' => $url,
                ]);

                return $url;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['billing' => 'PayPal checkout could not be started. Please try again.']);
        }
    }

    /** @param array<string, mixed> $options */
    public function startStripe(Workspace $workspace, Plan $plan, string $interval, string $priceId, array $options): string
    {
        try {
            return DB::transaction(function () use ($workspace, $plan, $interval, $priceId, $options): string {
                $locked = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
                $this->assertAvailable($locked);
                $plan = Plan::query()->lockForUpdate()->findOrFail($plan->id);
                if ($this->stripeReadiness->priceId($plan, $interval) !== $priceId) {
                    throw ValidationException::withMessages(['billing' => 'This plan has changed. Refresh billing and choose it again.']);
                }
                $checkout = $locked->newSubscription('default', $priceId)->checkout($options);
                $session = $checkout->asStripeCheckoutSession();
                if (empty($session->id) || ! is_string($session->url) || $session->url === '') {
                    throw new \RuntimeException('Invalid Stripe checkout response.');
                }
                PaymentCheckoutAttempt::query()->create([
                    'workspace_id' => $locked->id, 'plan_id' => $plan->id, 'provider' => 'stripe',
                    'interval' => $interval, 'reference' => (string) Str::uuid(),
                    'provider_id' => $session->id, 'approval_url' => $session->url,
                ]);

                return $session->url;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['billing' => 'Stripe checkout could not be started. Please try again.']);
        }
    }

    public function abandon(Workspace $workspace): void
    {
        try {
            DB::transaction(function () use ($workspace): void {
                $locked = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
                $attempt = PaymentCheckoutAttempt::query()->where('workspace_id', $locked->id)->lockForUpdate()->first();
                if ($attempt === null) {
                    return;
                }
                if ($attempt->provider === 'stripe') {
                    $sessions = $locked->stripe()->checkout->sessions;
                    $session = $sessions->retrieve($attempt->provider_id, []);
                    if ($session->status === 'complete') {
                        throw ValidationException::withMessages(['billing' => 'This checkout is complete. Wait for subscription confirmation before changing plans.']);
                    }
                    if ($session->status === 'open') {
                        $sessions->expire($attempt->provider_id, []);
                    } elseif ($session->status !== 'expired') {
                        throw new \RuntimeException('Unexpected Stripe checkout status.');
                    }
                } elseif ($attempt->provider === 'paypal') {
                    $subscription = PayPalSubscription::query()->where('workspace_id', $locked->id)
                        ->where('custom_id', $attempt->reference)->lockForUpdate()->firstOrFail();
                    $remote = $this->paypal->subscription($attempt->provider_id);
                    if (($remote['id'] ?? null) !== $attempt->provider_id || ($remote['custom_id'] ?? null) !== $attempt->reference) {
                        throw new \RuntimeException('Unexpected PayPal subscription.');
                    }
                    $status = $remote['status'] ?? null;
                    if (in_array($status, ['ACTIVE', 'SUSPENDED'], true)) {
                        throw ValidationException::withMessages(['billing' => 'This checkout is already approved. Wait for subscription confirmation before changing plans.']);
                    }
                    if (in_array($status, ['APPROVAL_PENDING', 'APPROVED'], true)) {
                        $this->paypal->cancelSubscription($attempt->provider_id, 'Customer cancelled checkout');
                    } elseif (! in_array($status, ['CANCELLED', 'EXPIRED'], true)) {
                        throw new \RuntimeException('Unexpected PayPal subscription status.');
                    }
                    $subscription->update(['status' => 'CANCELLED']);
                } else {
                    throw new \RuntimeException('Unknown checkout provider.');
                }
                $attempt->delete();
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['billing' => 'The provider could not cancel this checkout. Try again before starting another.']);
        }
    }

    private function assertAvailable(Workspace $workspace): void
    {
        if ($this->hasSubscription($workspace)) {
            throw ValidationException::withMessages(['subscription' => 'Cancel or manage the existing paid subscription before starting another checkout.']);
        }
        if (PaymentCheckoutAttempt::query()->where('workspace_id', $workspace->id)->exists()) {
            throw ValidationException::withMessages(['billing' => 'Resume or cancel the checkout already in progress before starting another.']);
        }
    }

    private function assertPayPalWebhook(): void
    {
        try {
            $id = (string) config('services.paypal.webhook_id');
            $webhook = $this->paypal->webhook($id);
            $expectedUrl = rtrim((string) config('app.url'), '/').route('billing.paypal.webhook', [], false);
            $events = [];
            foreach (is_array($webhook['event_types'] ?? null) ? $webhook['event_types'] : [] as $event) {
                if (is_array($event) && is_string($event['name'] ?? null)) {
                    $events[] = $event['name'];
                }
            }
            $required = [
                'BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.UPDATED',
                'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED',
                'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
                'PAYMENT.SALE.COMPLETED',
            ];
            if (($webhook['id'] ?? null) !== $id || ($webhook['url'] ?? null) !== $expectedUrl
                || (! in_array('*', $events, true) && array_diff($required, $events) !== [])) {
                throw new \RuntimeException('PayPal webhook configuration is incomplete.');
            }
        } catch (Throwable) {
            throw ValidationException::withMessages(['billing' => 'PayPal webhook setup is incomplete. Please contact the platform administrator.']);
        }
    }

    private function hasStripeSubscription(Workspace $workspace): bool
    {
        return $workspace->subscriptions()->where(function ($query): void {
            $query->whereNotIn('stripe_status', ['canceled', 'incomplete_expired'])
                ->orWhere(fn ($grace) => $grace->where('stripe_status', 'canceled')->where('ends_at', '>', now()));
        })->exists();
    }

    private function isPayPalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && is_string($host) && ($host === 'paypal.com' || str_ends_with($host, '.paypal.com'));
    }
}
