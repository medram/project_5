<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $paypal_plan_id */
#[Fillable(['plan_id', 'paypal_plan_id', 'interval', 'currency', 'unit_amount', 'verified_at'])]
class PlanPayPalPrice extends Model
{
    protected $table = 'plan_paypal_prices';

    protected function casts(): array
    {
        return ['unit_amount' => 'integer', 'verified_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
