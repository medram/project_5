<?php

namespace Tests\Feature\Analytics;

use App\Models\BioPage;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrackingConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_rejects_granted_consent_until_privacy_configuration_is_complete(): void
    {
        $this->incompletePrivacyConfiguration();

        $response = $this->post('/tracking-consent', [
            'consent' => TrackingConsent::GRANTED,
            'return_path' => '/bio/privacy-creator',
        ]);

        $response->assertSessionHasErrors('consent');
        $response->assertCookieMissing(TrackingConsent::COOKIE_NAME);
    }

    public function test_production_accepts_denied_consent_without_privacy_configuration(): void
    {
        $this->incompletePrivacyConfiguration();

        $this->post('/tracking-consent', [
            'consent' => TrackingConsent::DENIED,
            'return_path' => '/bio/privacy-creator',
        ])
            ->assertRedirect('/bio/privacy-creator')
            ->assertCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::DENIED);
    }

    public function test_granted_consent_succeeds_with_a_180_day_cookie_once_privacy_configuration_is_complete(): void
    {
        $this->completePrivacyConfiguration();

        $response = $this->post('/tracking-consent', [
            'consent' => TrackingConsent::GRANTED,
            'return_path' => '/bio/privacy-creator',
        ]);

        $response
            ->assertRedirect('/bio/privacy-creator')
            ->assertCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED);

        $this->assertCookieExpiresInDays($response, 180);
    }

    public function test_non_production_granted_consent_remains_available_without_privacy_configuration(): void
    {
        $this->incompletePrivacyConfiguration();
        config()->set('app.env', 'testing');

        $response = $this->post('/tracking-consent', [
            'consent' => TrackingConsent::GRANTED,
            'return_path' => '/bio/privacy-creator',
        ]);

        $response
            ->assertRedirect('/bio/privacy-creator')
            ->assertCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED);

        $this->assertCookieExpiresInDays($response, 180);
    }

    public function test_consent_surface_data_reports_granting_unavailable_when_privacy_url_is_present_but_analytics_keys_are_incomplete(): void
    {
        $this->incompletePrivacyConfiguration('https://privacy.example.test/notice');
        config()->set('app.env', 'testing');
        $this->withoutVite();

        $owner = User::factory()->create();
        BioPage::factory()->for($owner)->create([
            'handle' => 'privacy-unavailable',
            'is_published' => true,
        ]);

        $response = $this->get('/bio/privacy-unavailable');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('bio-pages/public', false)
            ->where('privacyUrl', 'https://privacy.example.test/notice')
            ->where('canGrantConsent', false)
            ->where('trackingConsent', null));
    }

    public function test_consent_surface_data_reports_granting_available_when_privacy_configuration_is_complete(): void
    {
        $this->completePrivacyConfiguration();
        config()->set('app.env', 'testing');
        $this->withoutVite();

        $owner = User::factory()->create();
        BioPage::factory()->for($owner)->create([
            'handle' => 'privacy-available',
            'is_published' => true,
        ]);

        $response = $this->get('/bio/privacy-available');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('bio-pages/public', false)
            ->where('privacyUrl', 'https://privacy.example.test/notice')
            ->where('canGrantConsent', true));
    }

    public function test_privacy_consent_helper_reflects_all_required_configuration(): void
    {
        config()->set('services.linkpilot.analytics.privacy_url', 'https://privacy.example.test/notice');
        config()->set('services.linkpilot.analytics.hash_key', null);
        config()->set('services.linkpilot.analytics.hash_key_version', null);

        $this->assertFalse(app(TrackingConsent::class)->canGrant());

        config()->set('services.linkpilot.analytics.hash_key', 'test-analytics-key');

        $this->assertFalse(app(TrackingConsent::class)->canGrant());

        config()->set('services.linkpilot.analytics.hash_key_version', 'test-v1');

        $this->assertTrue(app(TrackingConsent::class)->canGrant());
    }

    private function incompletePrivacyConfiguration(?string $privacyUrl = null): void
    {
        config()->set('app.env', 'production');
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'gdpr', [
            'privacy_url' => $privacyUrl,
        ], $settings->version('gdpr'));
        $settings->updateSection($administrator, 'analytics', [
            'visitor_hash_key' => null,
            'visitor_hash_version' => null,
        ], $settings->version('analytics'));
    }

    private function completePrivacyConfiguration(): void
    {
        config()->set('app.env', 'production');
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'gdpr', [
            'privacy_url' => 'https://privacy.example.test/notice',
        ], $settings->version('gdpr'));
        $settings->updateSection($administrator, 'analytics', [
            'visitor_hash_key' => str_repeat('k', 32),
            'visitor_hash_version' => 'test-v1',
        ], $settings->version('analytics'));
    }

    private function assertCookieExpiresInDays(TestResponse $response, int $days): void
    {
        $cookie = collect($response->baseResponse->headers->getCookies())
            ->first(fn ($candidate) => $candidate->getName() === TrackingConsent::COOKIE_NAME);

        $this->assertNotNull($cookie);
        $this->assertEqualsWithDelta(
            now()->addDays($days)->getTimestamp(),
            $cookie->getExpiresTime(),
            10,
        );
    }
}
