<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\PlanPayPalPrice;
use App\Models\PlanStripePrice;

final class PayPalReadiness
{
    public function configured(): bool
    {
        return in_array(config('services.paypal.mode'), ['sandbox', 'live'], true)
            && filled(config('services.paypal.client_id'))
            && filled(config('services.paypal.secret'));
    }

    public function checkoutReady(): bool
    {
        return $this->configured()
            && filled(config('services.paypal.webhook_id'))
            && (bool) config('cashier.tax_policy_acknowledged', false);
    }

    public function price(Plan $plan, string $interval): ?PlanPayPalPrice
    {
        if (! $plan->is_active || $plan->code === 'free' || ! in_array($interval, ['month', 'year'], true)) {
            return null;
        }

        $id = $plan->paypalPlanIdFor($interval);
        if ($id === null) {
            return null;
        }

        $plan->loadMissing(['paypalPrices', 'stripePrices']);
        $price = $plan->paypalPrices->first(fn (PlanPayPalPrice $price): bool => $price->paypal_plan_id === $id && $price->interval === $interval
            && strtolower($price->currency) === strtolower($plan->currency)
        );

        if ($price === null) {
            return null;
        }

        $stripeId = $plan->stripePriceIdFor($interval);
        $stripe = $plan->stripePrices->first(fn (PlanStripePrice $mapping): bool => $mapping->stripe_price_id === $stripeId && $mapping->interval === $interval
            && $mapping->verified_at !== null && $mapping->unit_amount !== null
        );

        return $stripe !== null && ($stripe->unit_amount !== $price->unit_amount || strtolower($stripe->currency) !== strtolower($price->currency))
            ? null : $price;
    }
}
