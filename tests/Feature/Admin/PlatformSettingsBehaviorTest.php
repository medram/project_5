<?php

namespace Tests\Feature\Admin;

use App\Models\AnalyticsEvent;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\User;
use App\Services\Accounts\GoogleConnection;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $settings->updateSection($administrator, 'themes', [
            'default_scheme' => 'dark',
            'accent' => 'teal',
            'radius' => 'lg',
        ], $settings->version('themes'));
        $settings->updateSection($administrator, 'menus', [
            'header_items' => ['home', 'pricing', 'contact', 'login', 'register'],
            'footer_items' => ['contact', 'login'],
        ], $settings->version('menus'));
        $settings->updateSection($administrator, 'links', [
            'default_delivery' => 'splash',
            'targeting_enabled' => false,
            'pixels_enabled' => false,
            'domains_enabled' => false,
        ], $settings->version('links'));
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
            'header_slot' => '123456',
            'content_slot' => '654321',
            'consent_required' => true,
        ], $settings->version('ads'));
        $visitorHashKey = str_repeat('s', 32);
        $settings->updateSection($administrator, 'analytics', [
            'ga4_measurement_id' => 'G-ACME1234',
            'visitor_hash_key' => $visitorHashKey,
            'visitor_hash_version' => 'v1',
        ], $settings->version('analytics'));
        $settings->updateSection($administrator, 'gdpr', [
            'privacy_url' => 'https://privacy.acme.test',
        ], $settings->version('gdpr'));

        $response = $this->withoutVite()->get('/');
        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('platform.siteName', 'Acme Links')
                ->where('platform.defaultDelivery', 'splash')
                ->where('name', 'Acme Links')
                ->where('platform.visibility.pricing', true)
                ->where('platform.seo.description', 'Acme description')
                ->where('platform.ads.provider', 'adsense')
                ->where('platform.ads.publisherId', 'ca-pub-1234567890123456')
                ->where('platform.ads.headerSlot', '123456')
                ->where('platform.ads.contentSlot', '654321')
                ->where('platform.ads.consentRequired', true)
                ->where('platform.analytics.ga4', 'G-ACME1234')
                ->where('platform.analytics.consentEnabled', true)
                ->where('trackingConsent', null)
                ->where('canGrantConsent', true)
                ->where('privacyUrl', 'https://privacy.acme.test')
                ->where('platform.theme.scheme', 'dark')
                ->where('platform.theme.accent', 'teal')
                ->where('platform.theme.radius', 'lg')
                ->where('platform.capabilities.targeting', false)
                ->where('platform.capabilities.pixels', false)
                ->where('platform.capabilities.domains', false)
                ->where('platform.menus.header', [
                    ['label' => 'Home', 'href' => route('home')],
                    ['label' => 'Pricing', 'href' => route('pricing')],
                    ['label' => 'Contact', 'href' => 'mailto:help@acme.test'],
                    ['label' => 'Login', 'href' => route('login')],
                    ['label' => 'Register', 'href' => route('register')],
                ])
                ->where('platform.menus.footer', [
                    ['label' => 'Contact', 'href' => 'mailto:help@acme.test'],
                    ['label' => 'Login', 'href' => route('login')],
                ]));
        $response->assertSee('data-platform-scheme="dark"', false)
            ->assertSee('data-platform-accent="teal"', false)
            ->assertSee('data-platform-radius="lg"', false);
        $response->assertDontSee($visitorHashKey);
    }

    public function test_subscription_readiness_shows_stripe_mode_and_webhook_url_without_secrets(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $stripeSecret = 'sk_test_readiness_secret';
        $webhookSecret = 'whsec_readiness_secret';
        $settings->updateSection($administrator, 'subscriptions', [
            'stripe_publishable_key' => 'pk_test_readiness',
            'stripe_secret' => $stripeSecret,
            'stripe_webhook_secret' => $webhookSecret,
            'tax_policy_acknowledged' => true,
        ], $settings->version('subscriptions'));

        $response = $this->withoutVite()
            ->actingAs($administrator)
            ->get('/admin/settings/subscriptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('section.readiness.state', 'ready')
                ->has('section.readiness.items', 8)
                ->where('section.readiness.items.4.label', 'Stripe mode')
                ->where('section.readiness.items.4.detail', 'Test mode')
                ->where('section.readiness.items.5.label', 'Webhook URL')
                ->where('section.readiness.items.5.detail', route('cashier.webhook')));

        $response->assertDontSee($stripeSecret)->assertDontSee($webhookSecret);
    }

    public function test_mismatched_stripe_key_modes_are_incomplete_and_redacted(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $stripeSecret = 'sk_test_mismatch_secret';
        $webhookSecret = 'whsec_mismatch_secret';
        $settings->updateSection($administrator, 'subscriptions', [
            'stripe_publishable_key' => 'pk_live_mismatch',
            'stripe_secret' => $stripeSecret,
            'stripe_webhook_secret' => $webhookSecret,
            'tax_policy_acknowledged' => true,
        ], $settings->version('subscriptions'));

        $response = $this->withoutVite()
            ->actingAs($administrator)
            ->get('/admin/settings/subscriptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('section.readiness.state', 'incomplete')
                ->where('section.readiness.items.4.state', 'incomplete')
                ->where('section.readiness.items.4.detail', 'Publishable and secret keys must use the same test or live mode.'));

        $response->assertDontSee($stripeSecret)->assertDontSee($webhookSecret);
    }

    public function test_consent_notice_position_and_policy_links_follow_platform_settings(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'gdpr', [
            'consent_notice' => 'Choose whether to allow optional analytics.',
            'consent_position' => 'corner',
            'privacy_url' => 'https://privacy.example.test/policy',
            'terms_url' => 'https://terms.example.test/terms',
        ], $settings->version('gdpr'));

        $this->withoutVite()->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('platform.gdpr.notice', 'Choose whether to allow optional analytics.')
                ->where('platform.gdpr.position', 'corner')
                ->where('platform.gdpr.defaultDenied', true)
                ->where('platform.gdpr.privacyUrl', 'https://privacy.example.test/policy')
                ->where('platform.gdpr.termsUrl', 'https://terms.example.test/terms')
                ->where('privacyUrl', 'https://privacy.example.test/policy'));
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

    public function test_dashboard_home_uses_platform_setting(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'general', [
            'dashboard_home' => 'analytics',
        ], $settings->version('general'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('analytics.index'));

        $settings->updateSection($administrator, 'general', [
            'dashboard_home' => 'links',
        ], $settings->version('general'));
        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('links.index'));
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
            'on_registration' => true,
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

        $this->actingAs($owner)->get('/dashboard/links')
            ->assertInertia(fn (Assert $page) => $page
                ->where('platform.defaultDelivery', 'splash'));

        $routingRules = [[
            'rule_type' => 'device',
            'match_value' => 'mobile',
            'target_url' => 'https://example.test/mobile',
        ]];
        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://example.test/existing',
            'title' => 'Existing rule',
            'slug' => 'existinglink',
            'is_active' => true,
            'routing_rules' => $routingRules,
        ])->assertRedirect('/dashboard/links');
        $targetedLink = Link::query()->where('slug', 'existinglink')->sole();

        $settings->updateSection($administrator, 'links', [
            'targeting_enabled' => false,
            'domains_enabled' => false,
            'domain_blocklist' => 'blocked.test',
            'keyword_blocklist' => 'casino',
            'slug_min' => 10,
            'slug_charset' => 'alphanumeric',
        ], $settings->version('links'));

        $this->actingAs($owner)->patch("/dashboard/links/{$targetedLink->id}", [
            'destination_url' => 'https://example.test/existing',
            'delivery_mode' => 'splash',
            'title' => 'Updated existing rule',
            'slug' => 'existinglink',
            'is_active' => true,
            'routing_rules' => $routingRules,
        ])->assertRedirect('/dashboard/links');
        $targetedLink->refresh();
        $this->assertSame('Updated existing rule', $targetedLink->title);
        $this->assertSame(1, $targetedLink->routingRules()->count());

        $this->actingAs($owner)->patch("/dashboard/links/{$targetedLink->id}", [
            'destination_url' => 'https://example.test/existing',
            'delivery_mode' => 'splash',
            'title' => 'Changed target rule',
            'slug' => 'existinglink',
            'is_active' => true,
            'routing_rules' => [[
                'rule_type' => 'device',
                'match_value' => 'mobile',
                'target_url' => 'https://example.test/changed',
            ]],
        ])->assertSessionHasErrors('routing_rules');

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

    public function test_splash_seconds_and_maintenance_banner_are_projected_to_public_surfaces(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'links', [
            'splash_seconds' => 9,
        ], $settings->version('links'));
        $settings->updateSection($administrator, 'system', [
            'maintenance_banner' => 'Scheduled maintenance tonight.',
        ], $settings->version('system'));

        $link = Link::factory()->create([
            'slug' => 'splash-countdown',
            'delivery_mode' => 'splash',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->withoutVite();
        $this->get('/'.$link->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/splash')
                ->where('seconds', 9));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('platform.maintenanceBanner', 'Scheduled maintenance tonight.'));
    }

    public function test_bio_allowlist_rejects_disallowed_creations_and_branding_is_opt_in(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'biolinks', [
            'available_blocks' => ['link', 'text'],
            'branding_enabled' => false,
        ], $settings->version('biolinks'));

        $page = BioPage::factory()->create([
            'handle' => 'allowlist-page',
            'is_published' => true,
        ]);

        $this->actingAs($page->user)
            ->post('/dashboard/biolinks/'.$page->id.'/blocks', [
                'type' => 'embed',
                'provider' => 'youtube',
                'identifier' => 'dQw4w9WgXcQ',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('bio_content_blocks', 0);

        $this->withoutVite();
        $this->get('/bio/allowlist-page')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('branding', null));
    }

    public function test_mail_and_analytics_retention_settings_are_consumed(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'email', [
            'mailer' => 'log',
            'sender_name' => 'Acme',
            'sender_address' => 'noreply@acme.test',
        ], $settings->version('email'));

        $this->get('/');
        $this->assertSame('log', config('mail.default'));
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
