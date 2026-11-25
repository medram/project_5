<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string|null $paypal_subscription_id */
#[Fillable(['workspace_id', 'plan_paypal_price_id', 'custom_id', 'paypal_subscription_id', 'status', 'last_paid_at', 'next_billing_at'])]
class PayPalSubscription extends Model
{
    protected $table = 'paypal_subscriptions';

    protected function casts(): array
    {
        return [
            'last_paid_at' => 'datetime',
            'next_billing_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PlanPayPalPrice, $this> */
    public function price(): BelongsTo
    {
        return $this->belongsTo(PlanPayPalPrice::class, 'plan_paypal_price_id');
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
