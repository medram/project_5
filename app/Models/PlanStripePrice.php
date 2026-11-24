<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['plan_id', 'stripe_price_id', 'interval', 'currency', 'unit_amount', 'verified_at'])]
class PlanStripePrice extends Model
{
    protected function casts(): array
    {
        return ['unit_amount' => 'integer', 'verified_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
