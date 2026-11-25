<?php

namespace Tests\Feature\Billing;

use App\Models\PayPalSubscription;
use App\Models\Plan;
use App\Models\PlanPayPalPrice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayPalBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconfigured_paypal_checkout_is_rejected_without_changing_the_plan(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/account-settings/billing')
            ->post('/billing/paypal/checkout/pro/month')
            ->assertRedirect('/account-settings/billing')
            ->assertSessionHasErrors('billing');

        $this->get('/account-settings/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('effectivePlan.code', 'free'));
    }

    public function test_checkout_requires_verified_payment_and_signed_webhook_before_granting_access(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        $signature = 'FAILURE';
        $this->fakePayPal($remote, $signature);

        $this->actingAs($user)->post('/billing/paypal/checkout/pro/month')
            ->assertRedirect('https://www.sandbox.paypal.com/subscribe?token=example');
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace)->code);
        $this->get('/billing/paypal/success?subscription_id=I-EXAMPLE')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('processing', true)->where('effectivePlan.code', 'free'));

        $event = ['id' => 'WH-1', 'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED', 'resource' => ['id' => 'I-EXAMPLE']];
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertForbidden();
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);

        $signature = 'SUCCESS';
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertNoContent();
        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
        $this->assertDatabaseMissing('payment_checkout_attempts', ['workspace_id' => $workspace->id]);
        $this->get('/account-settings/billing')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('effectiveSource', 'paypal')
            ->where('providerSubscription.provider', 'paypal')
            ->where('providerSubscription.baseAmount', 3499)
            ->where('billing.hasProviderSubscription', true));

        // A delayed cancellation event must not undo PayPal's current ACTIVE state.
        $event['event_type'] = 'BILLING.SUBSCRIPTION.CANCELLED';
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertNoContent();
        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
    }

    public function test_confirmed_cancellation_cannot_be_undone_by_a_stale_provider_response(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        $signature = 'SUCCESS';
        $this->fakePayPal($remote, $signature);
        $this->actingAs($user)->post('/billing/paypal/checkout/pro/month')->assertRedirect();
        $event = ['id' => 'WH-1', 'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED', 'resource' => ['id' => 'I-EXAMPLE']];
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertNoContent();
        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);

        $this->post('/billing/paypal/cancel')->assertRedirect('/account-settings/billing');
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertNoContent();
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
    }

    public function test_active_approval_without_payment_and_failed_payment_never_grant_access(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        unset($remote['billing_info']['last_payment']);
        $signature = 'SUCCESS';
        $this->fakePayPal($remote, $signature);
        $this->actingAs($user)->post('/billing/paypal/checkout/pro/month')->assertRedirect();

        $event = ['id' => 'WH-2', 'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED', 'resource' => ['id' => 'I-EXAMPLE']];
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertNoContent();
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);

        $remote = $this->remoteSubscription();
        $remote['billing_info']['last_payment']['amount']['value'] = '0.00';
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertNoContent();
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);

        $remote = $this->remoteSubscription();
        $remote['billing_info']['failed_payments_count'] = 1;
        $event['event_type'] = 'BILLING.SUBSCRIPTION.PAYMENT.FAILED';
        $this->postJson('/paypal/webhook', $event, $this->webhookHeaders())->assertNoContent();
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
        $this->assertDatabaseHas('paypal_subscriptions', ['workspace_id' => $workspace->id, 'status' => 'PAYMENT_FAILED']);

        $remote['billing_info']['failed_payments_count'] = 0;
        $this->postJson('/paypal/webhook', ['id' => 'WH-3', 'event_type' => 'PAYMENT.SALE.COMPLETED', 'resource' => ['billing_agreement_id' => 'I-EXAMPLE']], $this->webhookHeaders())->assertNoContent();
        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
    }

    public function test_expired_paypal_billing_period_does_not_grant_indefinite_access(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        $remote['billing_info']['next_billing_time'] = now()->subMinute()->toIso8601String();
        $signature = 'SUCCESS';
        $this->fakePayPal($remote, $signature);
        $this->actingAs($user)->post('/billing/paypal/checkout/pro/month')->assertRedirect();
        $this->postJson('/paypal/webhook', [
            'id' => 'WH-EXPIRED', 'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'resource' => ['id' => 'I-EXAMPLE'],
        ], $this->webhookHeaders())->assertNoContent();

        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
    }

    public function test_pending_paypal_checkout_blocks_other_provider_and_can_be_cancelled(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        $remote['status'] = 'APPROVAL_PENDING';
        $signature = 'SUCCESS';
        $this->fakePayPal($remote, $signature);
        $this->actingAs($user)->post('/billing/paypal/checkout/pro/month')->assertRedirect();
        $this->from('/account-settings/billing')->post('/billing/paypal/checkout/pro/month')
            ->assertSessionHasErrors('billing');
        $this->patch('/account-settings/billing/plan', ['plan' => 'free'])->assertSessionHasErrors('subscription');
        $this->post('/billing/checkout/abandon')->assertRedirect('/account-settings/billing');
        $this->assertDatabaseMissing('payment_checkout_attempts', ['workspace_id' => $workspace->id]);
        $this->assertDatabaseHas('paypal_subscriptions', ['workspace_id' => $workspace->id, 'status' => 'CANCELLED']);
    }

    public function test_owner_can_refresh_pending_subscription_from_paypal_if_webhook_is_delayed(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        $signature = 'SUCCESS';
        $this->fakePayPal($remote, $signature);
        $this->actingAs($user)->post('/billing/paypal/checkout/pro/month')->assertRedirect();
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);

        $this->post('/billing/paypal/refresh')->assertRedirect('/account-settings/billing');
        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
        $this->assertDatabaseMissing('payment_checkout_attempts', ['workspace_id' => $workspace->id]);
    }

    public function test_checkout_rejects_a_paypal_plan_whose_provider_price_changed(): void
    {
        $user = User::factory()->create();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        $signature = 'SUCCESS';
        $this->fakePayPal($remote, $signature, '35.99');

        $this->actingAs($user)->from('/account-settings/billing')
            ->post('/billing/paypal/checkout/pro/month')->assertSessionHasErrors('billing');
        $this->assertDatabaseCount('paypal_subscriptions', 0);
        $this->assertDatabaseCount('payment_checkout_attempts', 0);
    }

    public function test_checkout_rejects_a_webhook_missing_required_subscription_events(): void
    {
        $user = User::factory()->create();
        $this->configurePayPal();
        $remote = $this->remoteSubscription();
        $signature = 'SUCCESS';
        $this->fakePayPal($remote, $signature, '34.99', false);

        $this->actingAs($user)->from('/account-settings/billing')
            ->post('/billing/paypal/checkout/pro/month')->assertSessionHasErrors('billing');
        $this->assertDatabaseCount('paypal_subscriptions', 0);
    }

    public function test_only_workspace_owners_can_start_or_cancel_paypal_checkout(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'Billing team', 'slug' => 'paypal-team', 'owner_id' => $owner->id]);
        $workspace->members()->attach($owner, ['role' => 'owner']);
        $workspace->members()->attach($member, ['role' => 'member']);
        DB::table('users')->where('id', $member->id)->update(['current_workspace_id' => $workspace->id]);

        $this->actingAs($member->fresh())->post('/billing/paypal/checkout/pro/month')->assertForbidden();
        $this->post('/billing/paypal/cancel')->assertForbidden();
        $this->post('/billing/checkout/abandon')->assertForbidden();
    }

    private function configurePayPal(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($admin, 'subscriptions', [
            'billing_enabled' => true, 'tax_policy_acknowledged' => true,
            'paypal_mode' => 'sandbox', 'paypal_client_id' => 'client',
            'paypal_secret' => 'secret', 'paypal_webhook_id' => 'webhook',
        ], $settings->version('subscriptions'));
        $settings->applyRuntime();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $plan->update(['paypal_monthly_plan_id' => 'P-EXAMPLE']);
        PlanPayPalPrice::query()->create([
            'plan_id' => $plan->id, 'paypal_plan_id' => 'P-EXAMPLE', 'interval' => 'month',
            'currency' => 'usd', 'unit_amount' => 3499, 'verified_at' => now(),
        ]);
    }

    private function remoteSubscription(): array
    {
        return [
            'id' => 'I-EXAMPLE', 'plan_id' => 'P-EXAMPLE', 'status' => 'ACTIVE',
            'billing_info' => [
                'failed_payments_count' => 0,
                'last_payment' => ['time' => now()->subDay()->toIso8601String(), 'amount' => ['currency_code' => 'USD', 'value' => '34.99']],
                'next_billing_time' => now()->addMonth()->toIso8601String(),
            ],
        ];
    }

    private function fakePayPal(array &$remote, string &$signature, string $planPrice = '34.99', bool $webhookComplete = true): void
    {
        Http::fake(function (Request $request) use (&$remote, &$signature, $planPrice, $webhookComplete) {
            if (str_ends_with($request->url(), '/v1/oauth2/token')) {
                return Http::response(['access_token' => 'token'], 200);
            }
            if (str_ends_with($request->url(), '/v1/notifications/verify-webhook-signature')) {
                return Http::response(['verification_status' => $signature], 200);
            }
            if (str_ends_with($request->url(), '/v1/notifications/webhooks/webhook')) {
                return Http::response([
                    'id' => 'webhook',
                    'url' => rtrim((string) config('app.url'), '/').route('billing.paypal.webhook', [], false),
                    'event_types' => [['name' => $webhookComplete ? '*' : 'BILLING.SUBSCRIPTION.ACTIVATED']],
                ], 200);
            }
            if (str_ends_with($request->url(), '/v1/billing/plans/P-EXAMPLE')) {
                return Http::response([
                    'id' => 'P-EXAMPLE', 'status' => 'ACTIVE', 'billing_cycles' => [[
                        'tenure_type' => 'REGULAR', 'total_cycles' => 0,
                        'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                        'pricing_scheme' => ['fixed_price' => ['currency_code' => 'USD', 'value' => $planPrice]],
                    ]],
                ], 200);
            }
            if (str_ends_with($request->url(), '/v1/billing/subscriptions') && $request->method() === 'POST') {
                return Http::response(['id' => 'I-EXAMPLE', 'status' => 'APPROVAL_PENDING', 'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/subscribe?token=example', 'method' => 'GET'],
                ]], 201);
            }
            if (str_ends_with($request->url(), '/v1/billing/subscriptions/I-EXAMPLE/cancel')) {
                return Http::response('', 204);
            }
            if (str_ends_with($request->url(), '/v1/billing/subscriptions/I-EXAMPLE')) {
                return Http::response([...$remote, 'custom_id' => PayPalSubscription::query()->sole()->custom_id], 200);
            }

            return Http::response([], 500);
        });
    }

    private function webhookHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/cert',
            'PAYPAL-TRANSMISSION-ID' => 'transmission',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
            'PAYPAL-TRANSMISSION-TIME' => '2026-11-25T10:00:00Z',
        ];
    }
}
