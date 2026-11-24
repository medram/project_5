<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PlanStripePrice;

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
        if (! $plan->is_active || $plan->code === 'free' || ! in_array($interval, ['month', 'year'], true)) {
            return null;
        }

        $priceId = $plan->stripePriceIdFor($interval);
        if ($priceId === null) {
            return null;
        }

        $plan->loadMissing('stripePrices');

        $price = $plan->stripePrices->first(fn (PlanStripePrice $price): bool =>
            $price->stripe_price_id === $priceId
            && $price->interval === $interval
            && $price->verified_at !== null
            && $price->unit_amount !== null
        );

        return $price === null ? null : $priceId;
    }

    public function intervalForPrice(string $priceId): ?string
    {
        if ($priceId === '') {
            return null;
        }

        return PlanStripePrice::query()
            ->where('stripe_price_id', $priceId)
            ->get()
            ->first(fn (PlanStripePrice $price): bool => $price->stripe_price_id === $priceId)
            ?->interval;
    }
}
