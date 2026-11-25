<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use App\Services\Settings\PlatformSettingsReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PayPalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_gateway_settings_keep_the_existing_url_and_show_the_new_label(): void
    {
        $this->withoutVite()->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings/subscriptions')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('section.key', 'subscriptions')
                ->where('section.label', 'Payment gateways')
                ->where('navigation', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['key'] === 'subscriptions' && $item['label'] === 'Payment gateways',
                )));
    }

    public function test_paypal_settings_are_encrypted_redacted_and_applied_at_runtime(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        $this->actingAs($admin)->patch('/admin/settings/subscriptions', [
            'version' => 0,
            'values' => [
                'paypal_mode' => 'live',
                'paypal_client_id' => 'paypal-client',
                'paypal_secret' => 'paypal-private-secret',
                'paypal_webhook_id' => 'webhook-id',
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $row = PlatformSetting::query()->where('section', 'subscriptions')->where('key', 'paypal_secret')->sole();
        $this->assertTrue($row->is_secret);
        $this->assertStringNotContainsString('paypal-private-secret', $row->value);
        $this->assertSame('paypal-private-secret', json_decode(Crypt::decryptString($row->value), true));
        $this->assertArrayNotHasKey('paypal_secret', $settings->resolved()['subscriptions']);
        $this->assertStringNotContainsString('paypal-private-secret', json_encode(Cache::get('platform-settings.resolved'), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('paypal-private-secret', json_encode($settings->publicPayload(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('paypal-private-secret', json_encode($settings->schema('subscriptions'), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('paypal-private-secret', AdminAuditLog::query()->get()->toJson());
        $secret = collect($settings->schema('subscriptions')['groups'])->flatMap(fn (array $group) => $group['fields'])->firstWhere('key', 'paypal_secret');
        $this->assertTrue($secret['configured']);
        $this->assertNull($secret['value']);

        $this->withoutVite()->actingAs($admin)->get('/admin/settings/subscriptions')
            ->assertOk()->assertDontSee('paypal-private-secret');

        $settings->applyRuntime();
        $this->assertSame('live', config('services.paypal.mode'));
        $this->assertSame('paypal-client', config('services.paypal.client_id'));
        $this->assertSame('paypal-private-secret', config('services.paypal.secret'));
        $this->assertSame('webhook-id', config('services.paypal.webhook_id'));
    }

    public function test_paypal_mode_must_be_sandbox_or_live(): void
    {
        $this->actingAs(User::factory()->admin()->create())->patch('/admin/settings/subscriptions', [
            'version' => 0,
            'values' => ['paypal_mode' => 'production'],
        ])->assertSessionHasErrors('values.paypal_mode');
        $this->assertSame('sandbox', app(PlatformSettings::class)->resolved()['subscriptions']['paypal_mode']);
    }

    public function test_active_paypal_subscriptions_prevent_switching_merchant_or_removing_credentials(): void
    {
        $admin = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($admin, 'subscriptions', [
            'paypal_mode' => 'sandbox', 'paypal_client_id' => 'client-one',
            'paypal_secret' => 'secret', 'paypal_webhook_id' => 'webhook',
        ], 0);
        $planId = DB::table('plans')->where('code', 'pro')->value('id');
        $priceId = DB::table('plan_paypal_prices')->insertGetId([
            'plan_id' => $planId, 'paypal_plan_id' => 'P-EXISTING', 'interval' => 'month',
            'currency' => 'usd', 'unit_amount' => 3499, 'verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('paypal_subscriptions')->insert([
            'workspace_id' => $admin->personalWorkspace()->sole()->id, 'plan_paypal_price_id' => $priceId,
            'custom_id' => (string) Str::uuid(), 'paypal_subscription_id' => 'I-EXISTING',
            'status' => 'ACTIVE', 'last_paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->patch('/admin/settings/subscriptions', [
            'version' => 1, 'values' => ['paypal_mode' => 'live'],
        ])->assertSessionHasErrors();
        $this->patch('/admin/settings/subscriptions', [
            'version' => 1, 'values' => ['paypal_client_id' => 'client-two'],
        ])->assertSessionHasErrors();
        $this->delete('/admin/settings/subscriptions/secrets/paypal_secret', ['version' => 1])
            ->assertSessionHasErrors();
        $this->assertSame('sandbox', $settings->resolved()['subscriptions']['paypal_mode']);
        $this->assertSame('client-one', $settings->resolved()['subscriptions']['paypal_client_id']);
        $this->assertSame('secret', $settings->secret('subscriptions', 'paypal_secret'));
    }

    public function test_paypal_readiness_is_optional_until_partially_configured(): void
    {
        $settings = app(PlatformSettings::class);
        $readiness = app(PlatformSettingsReadiness::class);
        $admin = User::factory()->admin()->create();
        $settings->updateSection($admin, 'subscriptions', [
            'stripe_publishable_key' => 'pk_test_example',
            'stripe_secret' => 'sk_test_example',
            'stripe_webhook_secret' => 'whsec_example',
            'tax_policy_acknowledged' => true,
        ], 0);

        $result = $readiness->forSection('subscriptions');
        $this->assertSame('ready', $result['state']);
        $this->assertSame('disabled', collect($result['items'])->firstWhere('label', 'PayPal')['state']);

        $settings->updateSection($admin, 'subscriptions', ['paypal_client_id' => 'client'], 1);
        $result = $readiness->forSection('subscriptions');
        $this->assertSame('incomplete', $result['state']);
        $items = collect($result['items'])->keyBy('label');
        $this->assertSame('ready', $items['PayPal client ID']['state']);
        $this->assertSame('incomplete', $items['PayPal secret']['state']);
        $this->assertSame('incomplete', $items['PayPal webhook ID']['state']);
        $this->assertSame(rtrim((string) config('app.url'), '/').route('billing.paypal.webhook', [], false), $items['PayPal webhook URL']['detail']);

        $settings->updateSection($admin, 'subscriptions', ['paypal_secret' => 'secret', 'paypal_webhook_id' => 'webhook'], 2);
        $this->assertSame('ready', $readiness->forSection('subscriptions')['state']);
        $settings->updateSection($admin, 'subscriptions', ['tax_policy_acknowledged' => false], 3);
        $this->assertSame('incomplete', $readiness->forSection('subscriptions')['state']);
    }

    public function test_paypal_only_configuration_is_ready_without_stripe_credentials(): void
    {
        $settings = app(PlatformSettings::class);
        $settings->updateSection(User::factory()->admin()->create(), 'subscriptions', [
            'paypal_client_id' => 'client', 'paypal_secret' => 'secret',
            'paypal_webhook_id' => 'webhook', 'tax_policy_acknowledged' => true,
        ], 0);

        $result = app(PlatformSettingsReadiness::class)->forSection('subscriptions');
        $this->assertSame('ready', $result['state']);
        $this->assertSame('disabled', collect($result['items'])->firstWhere('label', 'Stripe')['state']);
    }
}
