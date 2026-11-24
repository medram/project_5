<?php

namespace App\Services\Billing;

use Laravel\Cashier\Cashier;
use Stripe\Price;

class StripePlanPrices
{
    public function retrieve(string $priceId): Price
    {
        return Cashier::stripe()->prices->retrieve($priceId, []);
    }
}
