<?php

namespace Tests\Feature\Admin;

use App\Models\PayPalSubscription;
use App\Models\Plan;
use App\Models\PlanPayPalPrice;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PayPalAdminBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_subscription_is_visible_and_sensitive_identifiers_are_redacted(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($admin, 'subscriptions', [
            'paypal_client_id' => 'client', 'paypal_secret' => 'secret',
        ], $settings->version('subscriptions'));
        $user = User::factory()->create();
        $subscription = $this->subscription($user);
        $this->withoutVite();

        $response = $this->actingAs($admin)->get('/admin/subscriptions?q='.urlencode($user->email));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspaces.data.0.provider.name', 'paypal')
            ->where('workspaces.data.0.provider.status', 'ACTIVE')
            ->where('workspaces.data.0.provider.amount', 1200)
            ->where('workspaces.data.0.provider.canCancel', true));
        $response->assertDontSee($subscription->paypal_subscription_id);
        $response->assertDontSee($subscription->custom_id);
    }

    public function test_plan_filters_and_user_details_use_paypal_entitlements(): void
    {
        $admin = User::factory()->admin()->create();
        $paid = User::factory()->create();
        $unpaid = User::factory()->create();
        $this->subscription($paid);
        $this->subscription($unpaid, 'SUSPENDED');
        $this->withoutVite();

        $this->actingAs($admin)->get('/admin/users?plan=pro&q='.urlencode($paid->email))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)->where('users.data.0.plan.source', 'paypal')
            ->where('users.data.0.plan.subscriptionStatus', 'ACTIVE'));
        $this->actingAs($admin)->get('/admin/users?plan=pro&q='.urlencode($unpaid->email))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 0));
        $this->actingAs($admin)->get('/admin/users/'.$paid->id)
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('workspaces.0.plan.code', 'pro')->where('workspaces.0.plan.subscriptionStatus', 'ACTIVE'));
    }

    public function test_zero_price_and_unpaid_active_subscriptions_filter_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        $freePriceUser = User::factory()->create();
        $unpaidUser = User::factory()->create();
        $zero = $this->subscription($freePriceUser);
        $zero->price->update(['unit_amount' => 0]);
        $zero->update(['last_paid_at' => null]);
        $this->subscription($unpaidUser)->update(['last_paid_at' => null]);
        Plan::query()->where('code', 'pro')->update(['is_active' => false]);
        $this->withoutVite();

        $this->actingAs($admin)->get('/admin/users?plan=pro&q='.urlencode($freePriceUser->email))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
        $this->actingAs($admin)->get('/admin/users?plan=free&q='.urlencode($unpaidUser->email))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
    }

    public function test_only_authorized_admin_can_cancel_paypal_immediately(): void
    {
        config(['services.paypal.mode' => 'sandbox', 'services.paypal.client_id' => 'client', 'services.paypal.secret' => 'secret']);
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'token']), '*/cancel' => Http::response([], 204)]);
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $subscription = $this->subscription($user, 'PAYMENT_FAILED');
        $url = '/admin/subscriptions/'.$user->personalWorkspace->id.'/cancel';

        $this->actingAs($user)->post($url, ['provider' => 'paypal'])->assertForbidden();
        $this->actingAs($admin)->post($url, ['provider' => 'paypal'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('CANCELLED', $subscription->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'cancel_subscription', 'subject_id' => $user->personalWorkspace->id]);
        Http::assertSentCount(2);
    }

    private function subscription(User $user, string $status = 'ACTIVE'): PayPalSubscription
    {
        $price = PlanPayPalPrice::query()->create([
            'plan_id' => Plan::query()->where('code', 'pro')->sole()->id,
            'paypal_plan_id' => 'P-'.uniqid(), 'interval' => 'month', 'currency' => 'USD', 'unit_amount' => 1200, 'verified_at' => now(),
        ]);

        return PayPalSubscription::query()->create([
            'workspace_id' => $user->personalWorkspace->id, 'plan_paypal_price_id' => $price->id,
            'custom_id' => (string) Str::uuid(), 'paypal_subscription_id' => 'I-secret-'.uniqid(),
            'status' => $status, 'last_paid_at' => now(), 'next_billing_at' => now()->addMonth(),
        ]);
    }
}
