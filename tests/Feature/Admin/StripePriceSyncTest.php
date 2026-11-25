<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\StripePlanPrices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Stripe\Price;
use Tests\TestCase;

class StripePriceSyncTest extends TestCase
{
    use RefreshDatabase;
    private ?StripePlanPrices $fakePrices = null;


    private function prices(array $prices): void
    {
        if ($this->fakePrices !== null) {
            $this->fakePrices->prices = $prices;
            return;
        }
        $this->fakePrices = new class($prices) extends StripePlanPrices {
            public function __construct(public array $prices) {}
            public function retrieve(string $priceId): Price
            {
                $value = $this->prices[$priceId] ?? new \RuntimeException('Provider unavailable');
                if ($value instanceof \Throwable) {
                    throw $value;
                }
                return Price::constructFrom($value);
            }
        };
        $this->app->instance(StripePlanPrices::class, $this->fakePrices);
    }

    private function price(string $id, array $changes = []): array
    {
        return array_replace_recursive([
            'id' => $id, 'object' => 'price', 'active' => true,
            'billing_scheme' => 'per_unit', 'currency' => 'usd', 'unit_amount' => 3499,
            'type' => 'recurring', 'recurring' => ['interval' => 'month', 'interval_count' => 1, 'usage_type' => 'licensed'],
        ], $changes);
    }

    public function test_sync_persists_provider_amount_and_verified_mapping(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => null]);
        $this->prices(['price_new' => $this->price('price_new')]);
        $this->actingAs(User::factory()->admin()->create())->post('/admin/plans/pro/prices/month', [
            'price_id' => 'price_new', 'expected_current_price_id' => null,
        ])->assertSessionHasNoErrors();
        $this->assertSame('price_new', $plan->fresh()->stripe_monthly_price_id);
        $this->assertSame(3499, $plan->fresh()->monthly_price);
        $this->assertDatabaseHas('plan_stripe_prices', ['plan_id' => $plan->id, 'stripe_price_id' => 'price_new', 'interval' => 'month', 'currency' => 'usd', 'unit_amount' => 3499]);
        $this->assertNotNull(DB::table('plan_stripe_prices')->where('stripe_price_id', 'price_new')->value('verified_at'));
    }

    public function test_stripe_sync_cannot_publish_a_different_price_than_the_current_paypal_plan(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => null, 'paypal_monthly_plan_id' => 'P-PRICE']);
        DB::table('plan_paypal_prices')->insert([
            'plan_id' => $plan->id, 'paypal_plan_id' => 'P-PRICE', 'interval' => 'month',
            'currency' => 'usd', 'unit_amount' => 4000, 'verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->prices(['price_new' => $this->price('price_new')]);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/plans/pro/prices/month', [
                'price_id' => 'price_new', 'expected_current_price_id' => null,
            ])->assertSessionHasErrors('price_id');

        $this->assertNull($plan->fresh()->stripe_monthly_price_id);
        $this->assertDatabaseMissing('plan_stripe_prices', ['stripe_price_id' => 'price_new']);
    }

    public function test_invalid_provider_prices_and_stale_expectations_do_not_mutate_plan(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => null, 'monthly_price' => 1000]);
        $this->prices([
            'wrong_currency' => $this->price('wrong_currency', ['currency' => 'eur']),
            'wrong_interval' => $this->price('wrong_interval', ['recurring' => ['interval' => 'year']]),
            'provider_error' => new \RuntimeException('provider failure'),
            'valid' => $this->price('valid'),
        ]);
        $this->actingAs(User::factory()->admin()->create());
        foreach (['wrong_currency', 'wrong_interval', 'provider_error', 'missing_credentials'] as $id) {
            $this->post('/admin/plans/pro/prices/month', ['price_id' => $id, 'expected_current_price_id' => null])->assertSessionHasErrors();
        }
        $this->post('/admin/plans/pro/prices/month', ['price_id' => 'valid', 'expected_current_price_id' => 'price_stale'])->assertSessionHasErrors();
        $this->assertNull($plan->fresh()->stripe_monthly_price_id);
        $this->assertSame(1000, $plan->fresh()->monthly_price);
        $this->assertDatabaseMissing('plan_stripe_prices', ['stripe_price_id' => 'valid']);
    }

    public function test_missing_credentials_and_free_plan_do_not_change_prices(): void
    {
        config(['cashier.secret' => null]);
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => null]);
        $this->actingAs(User::factory()->admin()->create());
        $this->post('/admin/plans/pro/prices/month', ['price_id' => 'price_missing_credentials', 'expected_current_price_id' => null])->assertSessionHasErrors();
        $this->post('/admin/plans/free/prices/month', ['price_id' => 'price_free', 'expected_current_price_id' => null])->assertSessionHasErrors();
        $this->assertNull($plan->fresh()->stripe_monthly_price_id);
        $this->assertDatabaseMissing('plan_stripe_prices', ['stripe_price_id' => 'price_missing_credentials']);
    }

    public function test_duplicate_mapping_is_rejected(): void
    {
        $pro = Plan::where('code', 'pro')->firstOrFail();
        $business = Plan::where('code', 'business')->firstOrFail();
        $pro->update(['stripe_monthly_price_id' => null]);
        DB::table('plan_stripe_prices')->insert(['plan_id' => $business->id, 'stripe_price_id' => 'price_shared', 'interval' => 'month', 'currency' => 'usd', 'created_at' => now(), 'updated_at' => now()]);
        $this->prices(['price_shared' => $this->price('price_shared')]);
        $this->actingAs(User::factory()->admin()->create())->post('/admin/plans/pro/prices/month', ['price_id' => 'price_shared', 'expected_current_price_id' => null])->assertSessionHasErrors();
        $this->assertNull($pro->fresh()->stripe_monthly_price_id);
    }

    public function test_migration_rejects_duplicate_current_ids_before_creating_history(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('plan_stripe_prices');
        Plan::where('code', 'pro')->update(['stripe_monthly_price_id' => 'price_duplicate']);
        Plan::where('code', 'business')->update(['stripe_yearly_price_id' => 'price_duplicate']);
        $migration = require database_path('migrations/2026_11_22_000003_create_plan_stripe_prices_table.php');
        try {
            $migration->up();
            $this->fail('Duplicate price IDs must prevent migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('duplicate', strtolower($exception->getMessage()));
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('plan_stripe_prices'));
        }
    }

    public function test_history_ids_are_case_sensitive_and_rollback_rejects_unrepresentable_history(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => 'price_Case']);
        foreach (['price_Case', 'price_case'] as $id) {
            DB::table('plan_stripe_prices')->insert(['plan_id' => $plan->id, 'stripe_price_id' => $id, 'interval' => 'month', 'currency' => 'usd', 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame(2, DB::table('plan_stripe_prices')->where('plan_id', $plan->id)->count());
        $migration = require database_path('migrations/2026_11_22_000003_create_plan_stripe_prices_table.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_rollback_rejects_custom_plans(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail()->replicate();
        $plan->code = 'creator';
        $plan->save();
        $migration = require database_path('migrations/2026_11_22_000003_create_plan_stripe_prices_table.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_clear_verifies_a_price_referenced_only_by_subscription_item(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => 'price_item']);
        DB::table('plan_stripe_prices')->insert(['plan_id' => $plan->id, 'stripe_price_id' => 'price_item', 'interval' => 'month', 'currency' => 'usd', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create();
        $subscription = DB::table('subscriptions')->insertGetId(['workspace_id' => $user->personalWorkspace()->sole()->id, 'type' => 'default', 'stripe_id' => 'sub_item', 'stripe_status' => 'active', 'stripe_price' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('subscription_items')->insert(['subscription_id' => $subscription, 'stripe_id' => 'si_item', 'stripe_product' => 'prod_item', 'stripe_price' => 'price_item', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::factory()->admin()->create());
        $this->prices([]);
        $this->delete('/admin/plans/pro/prices/month', ['expected_current_price_id' => 'price_item'])->assertSessionHasErrors();
        $this->assertSame('price_item', $plan->fresh()->stripe_monthly_price_id);
        $this->prices(['price_item' => $this->price('price_item', ['unit_amount' => 1200, 'active' => false])]);
        $this->delete('/admin/plans/pro/prices/month', ['expected_current_price_id' => 'price_item'])->assertSessionHasNoErrors();
        $this->assertNull($plan->fresh()->stripe_monthly_price_id);
        $this->assertDatabaseHas('plan_stripe_prices', ['stripe_price_id' => 'price_item', 'unit_amount' => 1200]);
    }

    public function test_referenced_unverified_old_price_is_preserved_and_provider_failure_is_atomic(): void
    {
        $plan = Plan::where('code', 'pro')->firstOrFail();
        $plan->update(['stripe_monthly_price_id' => 'price_old', 'monthly_price' => 1000]);
        DB::table('plan_stripe_prices')->insert(['plan_id' => $plan->id, 'stripe_price_id' => 'price_old', 'interval' => 'month', 'currency' => 'usd', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create();
        DB::table('subscriptions')->insert(['workspace_id' => $user->personalWorkspace()->sole()->id, 'type' => 'default', 'stripe_id' => 'sub_old', 'stripe_status' => 'active', 'stripe_price' => 'price_old', 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs(User::factory()->admin()->create());
        $this->prices(['price_new' => $this->price('price_new')]);
        $this->post('/admin/plans/pro/prices/month', ['price_id' => 'price_new', 'expected_current_price_id' => 'price_old'])->assertSessionHasErrors();
        $this->assertSame('price_old', $plan->fresh()->stripe_monthly_price_id);
        $this->assertDatabaseMissing('plan_stripe_prices', ['stripe_price_id' => 'price_new']);
        $this->prices(['price_new' => $this->price('price_new'), 'price_old' => $this->price('price_old', ['active' => false, 'unit_amount' => 1200])]);
        $this->post('/admin/plans/pro/prices/month', ['price_id' => 'price_new', 'expected_current_price_id' => 'price_old'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plan_stripe_prices', ['stripe_price_id' => 'price_old', 'unit_amount' => 1200]);
        $this->assertSame('price_new', $plan->fresh()->stripe_monthly_price_id);
    }
}
