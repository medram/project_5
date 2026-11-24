<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\PlanCatalogPresenter;
use App\Services\Billing\StripeReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_current_price_is_not_available_until_verified(): void
    {
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => 'price_unverified']);

        $this->assertNull(app(StripeReadiness::class)->priceId($plan, 'month'));
    }

    public function test_only_exact_verified_current_prices_are_available_for_active_paid_plans(): void
    {
        $plan = Plan::query()->where('code', 'pro')->firstOrFail()->replicate();
        $plan->fill(['code' => 'creator', 'is_active' => true, 'stripe_monthly_price_id' => 'price_creator', 'stripe_yearly_price_id' => 'price_legacy']);
        $plan->save();
        DB::table('plan_stripe_prices')->insert([
            ['plan_id' => $plan->id, 'stripe_price_id' => 'price_creator', 'interval' => 'month', 'currency' => 'usd', 'unit_amount' => 3499, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['plan_id' => $plan->id, 'stripe_price_id' => 'price_legacy', 'interval' => 'year', 'currency' => 'usd', 'unit_amount' => null, 'verified_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ['plan_id' => $plan->id, 'stripe_price_id' => 'price_old', 'interval' => 'year', 'currency' => 'usd', 'unit_amount' => 10000, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $readiness = app(StripeReadiness::class);
        $this->assertSame('price_creator', $readiness->priceId($plan, 'month'));
        $this->assertNull($readiness->priceId($plan, 'year'));
        $data = app(PlanCatalogPresenter::class)->data($plan);
        $this->assertSame(3499, $data['monthlyPrice']);
        $this->assertNull($data['annualPrice']);
        $this->assertTrue($data['checkout']['month']);
        $this->assertFalse($data['checkout']['year']);
        $plan->update(['is_active' => false]);
        $this->assertNull($readiness->priceId($plan, 'month'));
        $this->assertSame('year', $readiness->intervalForPrice('price_old'));
        $this->assertNull($readiness->intervalForPrice('price_missing'));
        $free = Plan::query()->where('code', 'free')->firstOrFail();
        $this->assertNull($readiness->priceId($free, 'month'));
        $this->assertSame(0, app(PlanCatalogPresenter::class)->data($free)['monthlyPrice']);
    }

    public function test_custom_plan_checkout_reaches_configuration_guard_instead_of_rejecting_its_code(): void
    {
        $plan = Plan::query()->where('code', 'pro')->firstOrFail()->replicate();
        $plan->fill(['code' => 'creator', 'is_active' => true, 'stripe_monthly_price_id' => 'price_creator']);
        $plan->save();
        DB::table('plan_stripe_prices')->insert([
            'plan_id' => $plan->id, 'stripe_price_id' => 'price_creator', 'interval' => 'month', 'currency' => 'usd', 'unit_amount' => 3499, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        config(['cashier.key' => null, 'cashier.secret' => null]);
        $this->actingAs(User::factory()->create())
            ->from('/account-settings/billing')
            ->get('/billing/checkout/creator/month')
            ->assertRedirect('/account-settings/billing')
            ->assertSessionHasErrors('billing');
    }
}
