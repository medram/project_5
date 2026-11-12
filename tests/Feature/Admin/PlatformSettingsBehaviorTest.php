<?php

namespace Tests\Feature\Admin;

use App\Models\AnalyticsEvent;
use App\Models\Link;
use App\Models\User;
use App\Services\Accounts\GoogleConnection;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformSettingsBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_payload_renders_identity_menus_seo_verification_and_ads(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'general', [
            'site_name' => 'Acme Links',
            'support_email' => 'help@acme.test',
        ], $settings->version('general'));
        $settings->updateSection($administrator, 'landing', [
            'homepage' => 'home',
            'pricing_enabled' => true,
            'contact_enabled' => true,
            'registration_enabled' => true,
        ], $settings->version('landing'));
        $settings->updateSection($administrator, 'seo', [
            'default_title' => 'Acme',
            'default_description' => 'Acme description',
        ], $settings->version('seo'));
        $settings->updateSection($administrator, 'brand-verification', [
            'google_token' => 'google-token',
        ], $settings->version('brand-verification'));
        $settings->updateSection($administrator, 'ads', [
            'provider' => 'adsense',
            'publisher_id' => 'ca-pub-1234567890123456',
            'content_slot' => '123456',
            'consent_required' => true,
        ], $settings->version('ads'));

        $this->withoutVite();
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('platform.siteName', 'Acme Links')
                ->where('platform.visibility.pricing', true)
                ->where('platform.seo.description', 'Acme description')
                ->where('platform.verification.google', 'google-token')
                ->where('platform.ads.provider', 'adsense'));
    }

    public function test_homepage_and_pricing_visibility_follow_settings(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        $settings->updateSection($administrator, 'landing', [
            'homepage' => 'pricing',
            'pricing_enabled' => true,
        ], $settings->version('landing'));
        $this->get('/')->assertRedirect('/pricing');
        $this->get('/pricing')->assertOk();

        $settings->updateSection($administrator, 'landing', [
            'homepage' => 'home',
            'pricing_enabled' => false,
        ], $settings->version('landing'));
        $this->get('/pricing')->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_registration_switch_and_blocked_domain_are_enforced(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'authentication', [
            'registration_enabled' => false,
            'blocked_email_domains' => '',
        ], $settings->version('authentication'));

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Blocked',
            'email' => 'blocked@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $settings->updateSection($administrator, 'authentication', [
            'registration_enabled' => true,
            'blocked_email_domains' => "blocked.test\nother.test",
        ], $settings->version('authentication'));

        $this->post('/register', [
            'name' => 'Blocked',
            'email' => 'blocked@blocked.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'blocked@blocked.test']);
    }

    public function test_google_setting_can_disable_configured_provider(): void
    {
        config()->set('linkpilot.auth.google_enabled', true);
        config()->set('services.google.client_id', 'client');
        config()->set('services.google.client_secret', 'secret');

        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'authentication', [
            'google_enabled' => false,
        ], $settings->version('authentication'));

        $this->assertFalse(app(GoogleConnection::class)->enabled());
        $this->get(route('google.redirect'))->assertNotFound();
    }

    public function test_turnstile_fails_closed_and_checks_hostname_and_action(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'captcha', [
            'provider' => 'turnstile',
            'site_key' => 'site-key',
            'secret' => 'secret-key',
        ], $settings->version('captcha'));

        $payload = [
            'name' => 'Captcha User',
            'email' => 'captcha@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->post('/register', $payload)->assertSessionHasErrors('captcha');

        Http::fakeSequence('https://challenges.cloudflare.com/turnstile/v0/siteverify')
            ->push([
                'success' => true,
                'hostname' => 'wrong.test',
                'action' => 'register',
            ])
            ->push([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'register',
            ]);

        $this->post('/register', [...$payload, 'cf-turnstile-response' => 'token'])->assertSessionHasErrors('captcha');
        $this->post('/register', [...$payload, 'cf-turnstile-response' => 'token'])
            ->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', ['email' => 'captcha@example.test']);
    }

    public function test_link_defaults_toggles_and_blocklists_are_enforced_server_side(): void
    {
        $owner = User::factory()->create();
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        $settings->updateSection($administrator, 'links', [
            'default_delivery' => 'splash',
            'splash_seconds' => 4,
        ], $settings->version('links'));

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://allowed.example/launch',
            'title' => 'Default delivery',
            'slug' => 'default-delivery',
            'is_active' => true,
        ])->assertRedirect('/dashboard/links');
        $this->assertSame('splash', Link::query()->where('slug', 'default-delivery')->sole()->delivery_mode);

        $settings->updateSection($administrator, 'links', [
            'targeting_enabled' => false,
            'domains_enabled' => false,
            'domain_blocklist' => "blocked.test",
            'keyword_blocklist' => "casino",
            'slug_min' => 10,
            'slug_charset' => 'alphanumeric',
        ], $settings->version('links'));

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://sub.blocked.test/path',
            'title' => 'Blocked',
            'slug' => 'blocked-destination',
            'is_active' => true,
        ])->assertSessionHasErrors('destination_url');

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://example.test/casino-offer',
            'title' => 'Keyword',
            'slug' => 'blocked-keyword',
            'is_active' => true,
        ])->assertSessionHasErrors('destination_url');

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://example.test/routing',
            'title' => 'Routing',
            'slug' => 'routing-link',
            'is_active' => true,
            'routing_rules' => [[
                'rule_type' => 'device',
                'match_value' => 'mobile',
                'target_url' => 'https://example.test/mobile',
            ]],
        ])->assertSessionHasErrors('routing_rules');

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://example.test/short',
            'title' => 'Short slug',
            'slug' => 'abcd',
            'is_active' => true,
        ])->assertSessionHasErrors('slug');
    }

    public function test_mail_and_analytics_retention_settings_are_consumed(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'email', [
            'mailer' => 'array',
            'sender_name' => 'Acme',
            'sender_address' => 'noreply@acme.test',
        ], $settings->version('email'));

        $this->get('/');
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('Acme', config('mail.from.name'));

        $settings->updateSection($administrator, 'analytics', [
            'retention_days' => 30,
        ], $settings->version('analytics'));
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $link = Link::factory()->for($administrator)->create(['workspace_id' => $workspace->id]);
        foreach ([5, 40] as $days) {
            AnalyticsEvent::query()->create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'user_id' => $administrator->id,
                'link_id' => $link->id,
                'event_name' => 'link.clicked',
                'occurred_at' => now('UTC')->subDays($days),
                'visitor_hash' => hash('sha256', 'event-'.$days),
                'visitor_key_version' => 'v1',
            ]);
        }
        $this->artisan('linkpilot:prune-analytics')->assertExitCode(0);
        $this->assertDatabaseCount('analytics_events', 1);
    }
}
