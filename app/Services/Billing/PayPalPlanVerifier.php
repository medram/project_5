<?php

namespace App\Services\Billing;

use App\Models\Plan;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PayPalPlanVerifier
{
    public function __construct(private readonly PayPalClient $paypal) {}

    public function amount(string $id, Plan $plan, string $interval): int
    {
        try {
            $provider = $this->paypal->plan($id);
        } catch (Throwable) {
            throw ValidationException::withMessages(['plan_id' => 'Unable to retrieve the PayPal plan. Check the PayPal configuration and try again.']);
        }
        $cycles = $provider['billing_cycles'] ?? null;
        $cycle = is_array($cycles) && count($cycles) === 1 ? reset($cycles) : null;
        $scheme = is_array($cycle) ? ($cycle['pricing_scheme'] ?? null) : null;
        $price = is_array($scheme) ? ($scheme['fixed_price'] ?? null) : null;
        $currency = strtoupper($plan->currency);
        $value = is_array($price) ? ($price['value'] ?? null) : null;
        $validCurrencies = ['AUD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'ILS', 'MXN', 'MYR', 'NOK', 'NZD', 'PHP', 'PLN', 'SEK', 'SGD', 'THB', 'USD'];
        if (($provider['id'] ?? null) !== $id || ($provider['status'] ?? null) !== 'ACTIVE'
            || ! in_array($currency, $validCurrencies, true) || ! is_array($cycle)
            || ($cycle['tenure_type'] ?? null) !== 'REGULAR' || ($cycle['total_cycles'] ?? null) !== 0
            || ($cycle['frequency']['interval_unit'] ?? null) !== strtoupper($interval)
            || ($cycle['frequency']['interval_count'] ?? null) !== 1
            || ! is_array($scheme) || isset($scheme['pricing_model']) || isset($scheme['tiers'])
            || ! is_array($price) || strtoupper((string) ($price['currency_code'] ?? '')) !== $currency
            || ! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,14})(?:\.([0-9]{1,2}))?$/D', $value, $matches)
            || isset($provider['payment_preferences']['setup_fee']) || isset($provider['taxes'])) {
            throw ValidationException::withMessages(['plan_id' => 'The PayPal plan must have one matching, fixed recurring price with no additional charges.']);
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
