<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;

class CreatesFreeSubscription
{
    public function for(User $user): UserSubscription
    {
        return DB::transaction(function () use ($user): UserSubscription {
            $subscription = $user->subscription()->firstOrCreate([], [
                'plan_id' => Plan::query()->where('code', 'free')->sole()->id,
                'status' => 'active',
                'source' => 'manual',
            ]);

            $subscription->load('plan');
            $user->setRelation('subscription', $subscription);

            return $subscription;
        });
    }
}
