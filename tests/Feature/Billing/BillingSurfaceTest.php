<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_is_public_and_shows_the_catalog_without_entitlement(): void
    {
        $this->withoutVite();

        $this->get('/pricing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pricing/index')
                ->has('plans', 3)
                ->where('currentPlanCode', null)
                ->where('canManageBilling', false));
    }

    public function test_billing_disables_checkout_truthfully_when_stripe_is_unconfigured(): void
    {
        $this->withoutVite();
        $this->clearStripeConfiguration();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account-settings/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/index', false)
                ->where('effectivePlan.code', 'free')
                ->where('effectiveSource', 'free')
                ->where('providerSubscription', null)
                ->where('billing.checkoutReady', false)
                ->where('billing.portalReady', false)
                ->where('billing.missing.0', 'STRIPE_KEY'));

        $this->actingAs($user)
            ->from('/account-settings/billing')
            ->get('/billing/portal')
            ->assertRedirect('/account-settings/billing')
            ->assertSessionHasErrors('billing');

        $this->actingAs($user)
            ->from('/account-settings/billing')
            ->get('/billing/checkout/pro/month')
            ->assertRedirect('/account-settings/billing')
            ->assertSessionHasErrors('billing');
    }

    public function test_checkout_is_owner_only_and_rejects_hidden_or_invalid_selections(): void
    {
        $this->withoutVite();
        $this->clearStripeConfiguration();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Checkout team',
            'slug' => 'checkout-team-'.$owner->id,
            'owner_id' => $owner->id,
        ]);
        $workspace->members()->attach($owner, ['role' => 'owner']);
        $workspace->members()->attach($member, ['role' => 'member']);
        DB::table('users')->whereIn('id', [$owner->id, $member->id])->update(['current_workspace_id' => $workspace->id]);

        $this->actingAs($member->fresh())
            ->get('/billing/checkout/pro/month')
            ->assertForbidden();

        $this->actingAs($owner->fresh())
            ->get('/billing/checkout/free/month')
            ->assertNotFound();

        $this->actingAs($owner->fresh())
            ->get('/billing/checkout/pro/quarter')
            ->assertNotFound();
    }

    public function test_success_query_parameters_never_grant_entitlement(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/billing/checkout/success?session_id=fake&plan=business')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/checkout-result')
                ->where('status', 'success')
                ->where('processing', true)
                ->where('effectivePlan.code', 'free')
                ->where('effectiveSource', 'free'));

        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($user->personalWorkspace()->sole())->code);
    }

    public function test_provider_subscription_state_drives_effective_plan_and_redacted_props(): void
    {
        $this->withoutVite();
        $this->configureStripe();
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        Plan::query()->where('code', 'pro')->update(['stripe_monthly_price_id' => 'price_pro_month']);

        DB::table('subscriptions')->insert([
            'workspace_id' => $workspace->id,
            'type' => 'default',
            'stripe_id' => 'sub_billing_surface',
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro_month',
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/account-settings/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/index', false)
                ->where('effectivePlan.code', 'pro')
                ->where('effectiveSource', 'stripe')
                ->where('providerSubscription.status', 'active')
                ->where('providerSubscription.interval', 'month')
                ->where('billing.portalReady', true)
                ->where('billing.checkoutReady', true)
                ->missing('providerSubscription.stripe_id')
                ->missing('providerSubscription.customer')
                ->missing('providerSubscription.invoice'));

        $this->actingAs($user)
            ->patch('/account-settings/billing/plan', ['plan' => 'free'])
            ->assertSessionHasErrors('subscription');

        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
    }

    public function test_pricing_marks_the_current_workspace_plan_for_authenticated_owners(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/pricing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pricing/index')
                ->where('currentPlanCode', 'free')
                ->where('canManageBilling', true));
    }

    private function configureStripe(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'subscriptions', [
            'billing_enabled' => true,
            'stripe_publishable_key' => 'pk_test_surface',
            'stripe_secret' => 'sk_test_surface',
            'stripe_webhook_secret' => 'whsec_surface',
            'currency' => 'usd',
            'tax_policy_acknowledged' => true,
        ], $settings->version('subscriptions'));

        config([
            'cashier.key' => 'pk_test_surface',
            'cashier.secret' => 'sk_test_surface',
            'cashier.webhook.secret' => 'whsec_surface',
            'cashier.tax_policy_acknowledged' => true,
        ]);
    }

    private function clearStripeConfiguration(): void
    {
        config([
            'cashier.key' => null,
            'cashier.secret' => null,
            'cashier.webhook.secret' => null,
            'cashier.tax_policy_acknowledged' => false,
        ]);
    }
}
