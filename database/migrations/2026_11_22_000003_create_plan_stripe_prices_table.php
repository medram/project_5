<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('plans')->get();
        $seen = [];
        foreach ($plans as $plan) {
            foreach (['month' => 'stripe_monthly_price_id', 'year' => 'stripe_yearly_price_id'] as $interval => $column) {
                $id = $plan->$column;
                if ($id === null || $id === '') {
                    continue;
                }
                if (isset($seen[$id])) {
                    throw new RuntimeException('Duplicate Stripe price ID in current plan mappings: '.$id);
                }
                $seen[$id] = [$plan->id, $interval, $plan->currency];
            }
        }

        Schema::create('plan_stripe_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $price = $table->string('stripe_price_id');
            if (DB::getDriverName() === 'mysql') {
                $price->collation('utf8mb4_bin');
            }
            $price->unique();
            $table->string('interval');
            $table->string('currency', 3);
            $table->unsignedBigInteger('unit_amount')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        foreach ($seen as $id => [$planId, $interval, $currency]) {
            DB::table('plan_stripe_prices')->insert([
                'plan_id' => $planId, 'stripe_price_id' => $id, 'interval' => $interval,
                'currency' => $currency, 'unit_amount' => null, 'verified_at' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::table('plans')->whereNotIn('code', ['free', 'pro', 'business'])->exists()) {
            throw new RuntimeException('Cannot roll back Stripe price history while custom plans exist.');
        }
        $plans = DB::table('plans')->get()->keyBy('id');
        foreach (DB::table('plan_stripe_prices')->get() as $price) {
            $plan = $plans->get($price->plan_id);
            $column = match ($price->interval) {
                'month' => 'stripe_monthly_price_id',
                'year' => 'stripe_yearly_price_id',
                default => null,
            };
            if ($plan === null || $column === null || $plan->$column !== $price->stripe_price_id) {
                throw new RuntimeException('Cannot roll back Stripe price history while historical mappings exist.');
            }
        }
        Schema::dropIfExists('plan_stripe_prices');
    }
};
