<?php

namespace App\Services\Billing;

use App\Models\Plan;

/**
 * Reports whether provider-owned billing is fully configured. Nothing here
 * contacts Stripe; missing configuration must disable checkout truthfully.
 */
final class StripeReadiness
{
    public function keyConfigured(): bool
    {
        return filled(config('cashier.key'));
    }

    public function secretConfigured(): bool
    {
        return filled(config('cashier.secret'));
    }

    public function webhookSecretConfigured(): bool
    {
        return filled(config('cashier.webhook.secret'));
    }

    public function taxPolicyAcknowledged(): bool
    {
        return filter_var(config('cashier.tax_policy_acknowledged', false), FILTER_VALIDATE_BOOL);
    }

    public function configured(): bool
    {
        return $this->keyConfigured() && $this->secretConfigured();
    }

    public function checkoutReady(): bool
    {
        return $this->configured()
            && $this->webhookSecretConfigured()
            && $this->taxPolicyAcknowledged();
    }

    /**
     * @return list<string>
     */
    public function missing(bool $requireWebhook = true): array
    {
        $missing = [];

        if (! $this->keyConfigured()) {
            $missing[] = 'STRIPE_KEY';
        }

        if (! $this->secretConfigured()) {
            $missing[] = 'STRIPE_SECRET';
        }

        if ($requireWebhook && ! $this->webhookSecretConfigured()) {
            $missing[] = 'STRIPE_WEBHOOK_SECRET';
        }

        if ($requireWebhook && ! $this->taxPolicyAcknowledged()) {
            $missing[] = 'CASHIER_TAX_POLICY_ACKNOWLEDGED';
        }

        return $missing;
    }

    public function priceId(Plan $plan, string $interval): ?string
    {
        if (! $plan->is_active) {
            return null;
        }

        return $plan->stripePriceIdFor($interval);
    }

    public function intervalForPrice(string $priceId): ?string
    {
        if ($priceId === '') {
            return null;
        }

        $plan = Plan::query()
            ->where('stripe_monthly_price_id', $priceId)
            ->orWhere('stripe_yearly_price_id', $priceId)
            ->first();

        if ($plan === null || ! $plan->is_active) {
            return null;
        }

        return $plan->stripe_yearly_price_id === $priceId ? 'year' : 'month';
    }
}
