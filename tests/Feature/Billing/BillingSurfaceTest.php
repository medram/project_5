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

    public function test_platform_billing_policy_hides_annual_prices_and_blocks_checkout_without_losing_keys(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $settings = app(PlatformSettings::class);
        $publishableKey = 'pk_test_policy_readiness';
        $secretKey = 'sk_test_policy_secret';
        $webhookSecret = 'whsec_policy_secret';
        $settings->updateSection($user, 'subscriptions', [
            'billing_enabled' => false,
            'annual_display' => false,
            'stripe_publishable_key' => $publishableKey,
            'stripe_secret' => $secretKey,
            'stripe_webhook_secret' => $webhookSecret,
        ], $settings->version('subscriptions'));

        $this->get('/pricing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('annualDisplay', false)
                ->where('billingEnabled', false));

        $billing = $this->actingAs($user)
            ->get('/account-settings/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('billing.enabled', false)
                ->where('billing.annualDisplay', false));
        $billing->assertDontSee($publishableKey)
            ->assertDontSee($secretKey)
            ->assertDontSee($webhookSecret);

        $this->from('/account-settings/billing')
            ->get('/billing/checkout/pro/month')
            ->assertRedirect('/account-settings/billing')
            ->assertSessionHasErrors('billing');

        $this->assertSame($publishableKey, $settings->resolved()['subscriptions']['stripe_publishable_key']);
        $this->assertSame($secretKey, $settings->secret('subscriptions', 'stripe_secret'));
        $this->assertSame($webhookSecret, $settings->secret('subscriptions', 'stripe_webhook_secret'));
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
        DB::table('plan_stripe_prices')->insert([
            'plan_id' => Plan::query()->where('code', 'pro')->value('id'),
            'stripe_price_id' => 'price_pro_month', 'interval' => 'month',
            'currency' => 'usd', 'unit_amount' => 3499, 'verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

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
                ->where('providerSubscription.baseAmount', 3499)
                ->where('providerSubscription.currency', 'usd')
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

    public function test_stripe_webhook_uses_database_secret_and_exempts_only_its_path_from_csrf(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'subscriptions', [
            'stripe_webhook_secret' => 'whsec_database',
        ], $settings->version('subscriptions'));
        $payload = json_encode(['id' => 'evt_platform_settings', 'type' => 'unhandled.test'], JSON_THROW_ON_ERROR);
        $timestamp = time();
        config(['cashier.webhook.secret' => 'whsec_legacy_environment']);

        $this->assertSame(
            ['stripe/webhook'],
            app(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)->getExcludedPaths(),
        );

        $legacySignature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_legacy_environment');
        $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$legacySignature}",
        ], $payload)->assertForbidden();

        $databaseSignature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_database');
        $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$databaseSignature}",
        ], $payload)->assertOk();
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
