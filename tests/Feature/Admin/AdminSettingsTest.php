<?php

namespace Tests\Feature\Admin;

use App\Models\AdminAuditLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use App\Services\Settings\PlatformSettings;
use App\Services\Settings\SettingDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_require_view_and_manage_permissions(): void
    {
        $administrator = User::factory()->admin()->create();
        $support = User::factory()->support()->create();

        $this->actingAs($support)->get('/admin/settings/general')->assertForbidden();
        $this->withoutVite();
        $this->actingAs($administrator)
            ->get('/admin/settings/general')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/section')
                ->where('section.key', 'general')
                ->has('navigation', 18)
                ->has('section.groups'));

        $this->actingAs($administrator)
            ->get('/admin/settings/not-a-section')
            ->assertNotFound();

        $this->actingAs($administrator)
            ->patch('/admin/settings/general', [
                'version' => 0,
                'values' => ['site_name' => 'Changed'],
            ])
            ->assertRedirect();
    }

    public function test_typed_values_are_validated_persisted_and_audited(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $before = $settings->resolved();

        $response = $this->actingAs($administrator)
            ->patch('/admin/settings/links', [
                'version' => 0,
                'values' => [
                    'default_delivery' => 'splash',
                    'slug_min' => 4,
                    'slug_max' => 40,
                    'slug_charset' => 'alphanumeric',
                    'targeting_enabled' => true,
                    'pixels_enabled' => false,
                    'domains_enabled' => true,
                    'splash_seconds' => 7,
                    'keyword_blocklist' => "spam\nabuse",
                    'domain_blocklist' => 'bad.test',
                ],
            ]);
        $response->assertRedirect();

        $after = $settings->resolved();
        $this->assertSame('splash', $after['links']['default_delivery']);
        $this->assertSame(4, $after['links']['slug_min']);
        $this->assertTrue($after['links']['targeting_enabled']);
        $this->assertSame('spam', explode("\n", $after['links']['keyword_blocklist'])[0]);
        $this->assertNotSame($before['links']['default_delivery'], $after['links']['default_delivery']);
        $this->assertDatabaseHas('admin_audit_logs', [
            'permission' => 'admin.settings.manage',
            'action' => 'update_platform_settings',
        ]);

        $this->actingAs($administrator)
            ->patch('/admin/settings/links', [
                'version' => 1,
                'values' => ['slug_min' => 1],
            ])
            ->assertSessionHasErrors('values.slug_min');

        $this->actingAs($administrator)
            ->patch('/admin/settings/links', [
                'version' => 1,
                'values' => ['unknown_key' => 'nope'],
            ])
            ->assertSessionHasErrors('values');
    }

    public function test_secret_values_are_encrypted_exactly_addressable_never_cached_and_clearable(): void
    {
        config()->set('services.turnstile.secret', 'environment-secret');
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        $this->actingAs($administrator)
            ->patch('/admin/settings/captcha', [
                'version' => 0,
                'values' => [
                    'provider' => 'turnstile',
                    'site_key' => 'site-key-public',
                    'secret' => 'super-secret-value',
                    'on_registration' => true,
                ],
            ])
            ->assertRedirect();

        $row = PlatformSetting::query()
            ->where('section', 'captcha')
            ->where('key', 'secret')
            ->sole();
        $this->assertTrue($row->is_secret);
        $this->assertStringNotContainsString('super-secret-value', (string) $row->value);
        $this->assertSame('super-secret-value', json_decode(Crypt::decryptString((string) $row->value), true));
        $this->assertSame('super-secret-value', $settings->secret('captcha', 'secret'));
        $this->assertArrayNotHasKey('secret', $settings->resolved()['captcha']);
        $this->assertStringNotContainsString('super-secret-value', json_encode(Cache::get('platform-settings.resolved'), JSON_THROW_ON_ERROR));

        $schema = $settings->schema('captcha');
        $this->assertSame('access', $schema['category']);
        $this->assertArrayNotHasKey('fields', $schema);
        $secret = collect($schema['groups'])
            ->flatMap(fn (array $group): array => $group['fields'])
            ->firstWhere('key', 'secret');
        $this->assertTrue($secret['configured']);
        $this->assertNull($secret['value']);
        $this->assertArrayNotHasKey('source', $secret);
        $this->assertArrayNotHasKey('readOnly', $secret);

        $this->actingAs($administrator)
            ->patch('/admin/settings/captcha', [
                'version' => 1,
                'values' => ['secret' => ''],
            ])
            ->assertRedirect();

        $this->assertSame(1, $settings->version('captcha'));
        $this->assertSame('super-secret-value', $settings->secret('captcha', 'secret'));

        $settings->clearSecret($administrator, 'captcha', 'secret', 1);

        $this->assertSame(2, $settings->version('captcha'));
        $this->assertNull($settings->secret('captcha', 'secret'));
        $this->assertNull(json_decode(Crypt::decryptString((string) $row->refresh()->value), true));
        $clearedSecret = collect($settings->schema('captcha')['groups'])
            ->flatMap(fn (array $group): array => $group['fields'])
            ->firstWhere('key', 'secret');
        $this->assertFalse($clearedSecret['configured']);
        $this->assertSame(
            ['section' => 'captcha', 'key' => 'secret'],
            AdminAuditLog::query()->where('action', 'clear_platform_setting_secret')->sole()->changes,
        );

        $this->expectException(ValidationException::class);
        $settings->clearSecret($administrator, 'captcha', 'site_key', 2);
    }

    public function test_durable_section_revisions_increment_once_only_when_values_change(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        $this->assertDatabaseCount('platform_setting_sections', 18);
        $this->assertDatabaseHas('platform_setting_sections', [
            'section' => 'general',
            'revision' => 0,
        ]);
        $this->assertFalse(Schema::hasColumn('platform_settings', 'version'));

        $settings->updateSection($administrator, 'general', ['site_name' => 'Acme'], 0);

        $this->assertSame(1, $settings->version('general'));
        $this->assertSame(
            ['section' => 'general', 'keys' => ['site_name']],
            AdminAuditLog::query()->where('action', 'update_platform_settings')->sole()->changes,
        );

        $settings->updateSection($administrator, 'general', ['site_name' => 'Acme'], 1);

        $this->assertSame(1, $settings->version('general'));
        $this->assertSame(1, AdminAuditLog::query()->where('action', 'update_platform_settings')->count());

        $settings->updateSection($administrator, 'general', ['support_email' => 'support@acme.test'], 1);

        $this->assertSame(2, $settings->version('general'));
        $this->assertSame(
            ['section' => 'general', 'keys' => ['support_email']],
            AdminAuditLog::query()->where('action', 'update_platform_settings')->latest('id')->firstOrFail()->changes,
        );
    }

    public function test_process_revision_changes_when_two_writes_share_a_timestamp(): void
    {
        Carbon::setTestNow('2026-11-13 12:00:00');

        try {
            $administrator = User::factory()->admin()->create();
            $settings = app(PlatformSettings::class);

            $settings->updateSection($administrator, 'general', ['site_name' => 'First'], 0);
            $this->assertSame('First', $settings->resolved()['general']['site_name']);
            $firstRevision = Cache::get('platform-settings.resolved')['revision'];

            $settings->updateSection($administrator, 'general', ['site_name' => 'Second'], 1);
            $this->assertSame('Second', $settings->resolved()['general']['site_name']);
            $secondRevision = Cache::get('platform-settings.resolved')['revision'];

            $this->assertNotSame($firstRevision, $secondRevision);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_uploading_rejects_unknown_or_public_disks(): void
    {
        $administrator = User::factory()->admin()->create();

        foreach (['public', 'not-configured'] as $disk) {
            $this->actingAs($administrator)
                ->patch('/admin/settings/uploading', [
                    'version' => 0,
                    'values' => ['disk' => $disk],
                ])
                ->assertSessionHasErrors('values.disk');
        }

        $this->assertSame('local', app(PlatformSettings::class)->resolved()['uploading']['disk']);
    }

    public function test_partial_smtp_updates_are_checked_against_stored_values(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'email', [
            'mailer' => 'smtp',
            'smtp_host' => 'smtp.acme.test',
            'smtp_port' => 587,
            'smtp_security' => 'starttls',
        ], $settings->version('email'));

        $this->actingAs($administrator)
            ->patch('/admin/settings/email', [
                'version' => 1,
                'values' => ['smtp_host' => ''],
            ])
            ->assertSessionHasErrors('values.smtp_host');

        $this->assertSame('smtp.acme.test', $settings->resolved()['email']['smtp_host']);
    }

    public function test_turnstile_protection_requires_complete_credentials(): void
    {
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->patch('/admin/settings/captcha', [
                'version' => 0,
                'values' => [
                    'provider' => 'turnstile',
                    'on_registration' => true,
                ],
            ])
            ->assertSessionHasErrors('values.site_key');
    }

    public function test_disabled_consent_permits_tracking_only_with_complete_privacy_configuration(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'analytics', [
            'consent_enabled' => false,
            'visitor_hash_key' => str_repeat('a', 32),
            'visitor_hash_version' => 'v1',
        ], $settings->version('analytics'));
        $settings->updateSection($administrator, 'gdpr', [
            'privacy_url' => 'https://example.test/privacy',
        ], $settings->version('gdpr'));
        $settings->applyRuntime();

        $consent = app(TrackingConsent::class);
        $this->assertTrue($consent->allows(Request::create('/')));

        $settings->clearSecret($administrator, 'analytics', 'visitor_hash_key', $settings->version('analytics'));
        $settings->applyRuntime();

        $this->assertFalse($this->consent()->allows(Request::create('/')));
    }

    private function consent(): TrackingConsent
    {
        return app(TrackingConsent::class);
    }

    public function test_stale_versions_and_invalid_bounds_are_rejected_without_writes(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        $this->actingAs($administrator)
            ->patch('/admin/settings/links', [
                'version' => 0,
                'values' => ['slug_min' => 5],
            ])
            ->assertRedirect();

        $this->actingAs($administrator)
            ->patch('/admin/settings/links', [
                'version' => 0,
                'values' => ['slug_min' => 6],
            ])
            ->assertSessionHasErrors('version');

        $this->actingAs($administrator)
            ->patch('/admin/settings/links', [
                'version' => 1,
                'values' => ['slug_min' => 999],
            ])
            ->assertSessionHasErrors('values.slug_min');

        $this->actingAs($administrator)
            ->patch('/admin/settings/ads', [
                'version' => 0,
                'values' => [
                    'provider' => 'adsense',
                    'publisher_id' => 'not-a-publisher',
                ],
            ])
            ->assertSessionHasErrors('values.publisher_id');

        $this->assertSame(1, $settings->version('links'));
        $this->assertSame(5, $settings->resolved()['links']['slug_min']);
    }

    public function test_registry_covers_the_fixed_settings_surface(): void
    {
        $definitions = app(SettingDefinitions::class);
        $expected = [
            'general', 'links', 'biolinks', 'landing', 'themes', 'menus',
            'subscriptions', 'localization', 'authentication', 'uploading',
            'email', 'system', 'analytics', 'brand-verification', 'captcha',
            'gdpr', 'seo', 'ads',
        ];

        $this->assertSame($expected, $definitions->sectionKeys());
        $this->assertCount(18, $definitions->sections());
    }
}
