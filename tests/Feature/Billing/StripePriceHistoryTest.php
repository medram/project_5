<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StripePriceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_price_and_item_only_subscription_retain_inactive_plan_and_billing_amount(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $plan->update(['is_active' => false, 'stripe_monthly_price_id' => 'price_new', 'monthly_price' => 9900]);
        $this->price($plan, 'price_old', 'month', 'usd', 3499, true);
        $this->subscription($workspace->id, 'old', null, 'price_old');

        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
        $this->actingAs($user)->get('/account-settings/billing')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('effectivePlan.code', 'pro')
                ->where('effectiveSource', 'stripe')
                ->where('providerSubscription.interval', 'month')
                ->where('providerSubscription.baseAmount', 3499)
                ->where('providerSubscription.currency', 'usd'));
    }

    public function test_unverified_history_keeps_entitlement_but_has_no_provider_amount(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $this->price($plan, 'price_unverified', 'year', 'usd', null, false);
        $this->subscription($workspace->id, 'unverified', 'price_unverified');

        $this->actingAs($user)->get('/account-settings/billing')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('effectivePlan.code', 'pro')
                ->where('providerSubscription.interval', 'year')
                ->where('providerSubscription.baseAmount', null)
                ->where('providerSubscription.currency', null));
    }

    public function test_insights_only_count_verified_matching_currency_historical_amounts(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->create();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $plan->update(['stripe_monthly_price_id' => 'price_new', 'monthly_price' => 9900]);
        $this->price($plan, 'price_old', 'month', 'usd', 3499, true);
        $this->price($plan, 'price_unverified', 'month', 'usd', null, false);
        $this->price($plan, 'price_foreign', 'month', 'eur', 5000, true);
        $this->subscription($admin->personalWorkspace()->sole()->id, 'old', null, 'price_old');
        $this->subscription(User::factory()->create()->personalWorkspace()->sole()->id, 'unverified', 'price_unverified');
        $this->subscription(User::factory()->create()->personalWorkspace()->sole()->id, 'foreign', 'price_foreign');

        $this->actingAs($admin)->get('/admin/insights?range=30')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('insights.subscriptions.mrrCents', 3499)
                ->where('insights.subscriptions.arrCents', 41988)
                ->where('insights.subscriptions.mappedSubscriptions', 1)
                ->where('insights.subscriptions.unmappedSubscriptions', 2));
    }

    public function test_admin_plan_filters_include_historical_subscription_items_and_exclude_them_from_free(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->create();
        $subscriber = User::factory()->create();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $plan->update(['stripe_monthly_price_id' => 'price_new']);
        $this->price($plan, 'price_old', 'month', 'usd', 3499, true);
        $this->subscription($subscriber->personalWorkspace()->sole()->id, 'old', null, 'price_old');

        $this->actingAs($admin)->get('/admin/users?plan=pro')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.id', $subscriber->id));
        $this->actingAs($admin)->get('/admin/users?plan=free')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.id', $admin->id));
    }

    public function test_admin_can_filter_subscribers_on_a_deactivated_plan(): void
    {
        $this->withoutVite();
        $admin = User::factory()->admin()->create();
        $subscriber = User::factory()->create();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $plan->update(['is_active' => false, 'stripe_monthly_price_id' => 'price_new']);
        $this->price($plan, 'price_old', 'month', 'usd', 3499, true);
        $this->subscription($subscriber->personalWorkspace()->sole()->id, 'old', 'price_old');

        $this->actingAs($admin)->get('/admin/users?plan=pro')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('users.total', 1)->where('users.data.0.id', $subscriber->id));
    }

    private function price(Plan $plan, string $id, string $interval, string $currency, ?int $amount, bool $verified): void
    {
        DB::table('plan_stripe_prices')->insert([
            'plan_id' => $plan->id, 'stripe_price_id' => $id, 'interval' => $interval,
            'currency' => $currency, 'unit_amount' => $amount, 'verified_at' => $verified ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function subscription(int $workspaceId, string $suffix, ?string $price, ?string $itemPrice = null): void
    {
        $id = DB::table('subscriptions')->insertGetId([
            'workspace_id' => $workspaceId, 'type' => 'default', 'stripe_id' => 'sub_'.$suffix,
            'stripe_status' => 'active', 'stripe_price' => $price, 'quantity' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($itemPrice !== null) {
            DB::table('subscription_items')->insert([
                'subscription_id' => $id, 'stripe_id' => 'si_'.$suffix, 'stripe_product' => 'prod_test',
                'stripe_price' => $itemPrice, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
