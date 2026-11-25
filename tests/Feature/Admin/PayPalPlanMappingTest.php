<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\PayPalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayPalPlanMappingTest extends TestCase
{
    use RefreshDatabase;

    private function provider(array $plans): void
    {
        $client = \Mockery::mock(PayPalClient::class);
        $client->shouldReceive('plan')->andReturnUsing(fn (string $id) => $plans[$id] ?? throw new \RuntimeException('Unavailable'));
        $this->app->instance(PayPalClient::class, $client);
    }

    private function providerPlan(string $id): array
    {
        return ['id' => $id, 'status' => 'ACTIVE', 'billing_cycles' => [[
            'tenure_type' => 'REGULAR', 'total_cycles' => 0,
            'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
            'pricing_scheme' => ['fixed_price' => ['currency_code' => 'USD', 'value' => '34.99']],
        ]]];
    }

    public function test_sync_and_clear_preserve_verified_history(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $this->provider(['P-ONE' => $this->providerPlan('P-ONE')]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post('/admin/plans/pro/paypal/month', ['plan_id' => 'P-ONE', 'expected_current_plan_id' => null])->assertSessionHasNoErrors();
        $this->assertSame('P-ONE', $plan->fresh()->paypal_monthly_plan_id);
        $this->assertDatabaseHas('plan_paypal_prices', ['plan_id' => $plan->id, 'paypal_plan_id' => 'P-ONE', 'unit_amount' => 3499]);
        $this->delete('/admin/plans/pro/paypal/month', ['expected_current_plan_id' => 'P-ONE'])->assertSessionHasNoErrors();
        $this->assertNull($plan->fresh()->paypal_monthly_plan_id);
        $this->assertDatabaseHas('plan_paypal_prices', ['paypal_plan_id' => 'P-ONE']);
    }

    public function test_rejects_invalid_provider_plans_and_stale_updates(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $valid = $this->providerPlan('P-BAD');
        $invalid = $valid;
        $invalid['billing_cycles'][] = ['tenure_type' => 'TRIAL', 'total_cycles' => 1];
        $this->provider(['P-BAD' => $invalid, 'P-GOOD' => $this->providerPlan('P-GOOD')]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post('/admin/plans/pro/paypal/month', ['plan_id' => 'P-BAD', 'expected_current_plan_id' => null])->assertSessionHasErrors();
        $this->post('/admin/plans/pro/paypal/month', ['plan_id' => 'P-GOOD', 'expected_current_plan_id' => 'P-STALE'])->assertSessionHasErrors();
        $this->assertNull($plan->fresh()->paypal_monthly_plan_id);
        $this->assertDatabaseCount('plan_paypal_prices', 0);
    }

    public function test_rejects_mismatched_verified_stripe_amount(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => 'price_verified']);
        DB::table('plan_stripe_prices')->insert(['plan_id' => $plan->id, 'stripe_price_id' => 'price_verified', 'interval' => 'month', 'currency' => 'usd', 'unit_amount' => 1000, 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->provider(['P-ONE' => $this->providerPlan('P-ONE')]);
        $this->actingAs(User::factory()->admin()->create())->post('/admin/plans/pro/paypal/month', ['plan_id' => 'P-ONE', 'expected_current_plan_id' => null])->assertSessionHasErrors();
        $this->assertNull($plan->fresh()->paypal_monthly_plan_id);
    }

    public function test_rejects_provider_charge_variations_without_mutating_history(): void
    {
        $base = $this->providerPlan('P-BAD');
        $cases = [];
        $case = $base;
        $case['status'] = 'INACTIVE';
        $cases[] = $case;
        $case = $base;
        $case['billing_cycles'][0]['total_cycles'] = 12;
        $cases[] = $case;
        $case = $base;
        $case['billing_cycles'][0]['frequency']['interval_unit'] = 'YEAR';
        $cases[] = $case;
        $case = $base;
        $case['billing_cycles'][0]['frequency']['interval_count'] = 2;
        $cases[] = $case;
        $case = $base;
        $case['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] = '1.001';
        $cases[] = $case;
        $case = $base;
        $case['billing_cycles'][0]['pricing_scheme']['fixed_price']['currency_code'] = 'EUR';
        $cases[] = $case;
        $case = $base;
        $case['billing_cycles'][0]['pricing_scheme']['pricing_model'] = 'VOLUME';
        $cases[] = $case;
        $case = $base;
        $case['payment_preferences']['setup_fee'] = ['currency_code' => 'USD', 'value' => '1.00'];
        $cases[] = $case;
        $case = $base;
        $case['taxes'] = ['percentage' => '10'];
        $cases[] = $case;
        $this->actingAs(User::factory()->admin()->create());
        foreach ($cases as $case) {
            $this->provider(['P-BAD' => $case]);
            $this->post('/admin/plans/pro/paypal/month', ['plan_id' => 'P-BAD', 'expected_current_plan_id' => null])->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('plan_paypal_prices', 0);
    }

    public function test_mapping_cannot_be_claimed_by_another_plan_and_provider_failure_is_atomic(): void
    {
        $this->provider(['P-ONE' => $this->providerPlan('P-ONE')]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post('/admin/plans/pro/paypal/month', ['plan_id' => 'P-ONE', 'expected_current_plan_id' => null])->assertSessionHasNoErrors();
        $this->post('/admin/plans/business/paypal/month', ['plan_id' => 'P-ONE', 'expected_current_plan_id' => null])->assertSessionHasErrors();
        $this->post('/admin/plans/pro/paypal/month', ['plan_id' => 'P-MISSING', 'expected_current_plan_id' => 'P-ONE'])->assertSessionHasErrors();
        $this->assertSame('P-ONE', Plan::where('code', 'pro')->firstOrFail()->paypal_monthly_plan_id);
        $this->assertDatabaseCount('plan_paypal_prices', 1);
    }
}
