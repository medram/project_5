<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use App\Services\Settings\SettingDefinitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
                ->has('section.fields'));

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
                    'domain_blocklist' => "bad.test",
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

    public function test_secret_values_are_encrypted_blank_preserves_and_ui_hides_value(): void
    {
        $administrator = User::factory()->admin()->create();
        $section = app(PlatformSettings::class)->schema('captcha');
        $this->assertNotNull($section);

        $this->actingAs($administrator)
            ->patch('/admin/settings/captcha', [
                'version' => 0,
                'values' => [
                    'provider' => 'turnstile',
                    'site_key' => 'site-key-public',
                    'secret' => 'super-secret-value',
                    'on_registration' => true,
                    'on_public_actions' => false,
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

        $schema = app(PlatformSettings::class)->schema('captcha');
        $secret = collect($schema['fields'])->firstWhere('key', 'secret');
        $this->assertTrue($secret['configured']);
        $this->assertNull($secret['value']);

        $this->actingAs($administrator)
            ->patch('/admin/settings/captcha', [
                'version' => 1,
                'values' => ['secret' => ''],
            ])
            ->assertRedirect();

        $this->assertSame(
            'super-secret-value',
            json_decode(Crypt::decryptString((string) $row->refresh()->value), true),
        );
    }

    public function test_environment_values_win_and_are_read_only(): void
    {
        config()->set('services.turnstile.secret', 'environment-secret');
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $schema = $settings->schema('captcha');
        $secret = collect($schema['fields'])->firstWhere('key', 'secret');

        $this->assertSame('environment', $secret['source']);
        $this->assertTrue($secret['readOnly']);
        $this->assertTrue($secret['configured']);
        $this->assertNull($secret['value']);
        $this->assertSame('environment-secret', $settings->resolved()['captcha']['secret']);

        $this->actingAs($administrator)
            ->patch('/admin/settings/captcha', [
                'version' => 0,
                'values' => ['secret' => 'database-secret'],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('platform_settings', [
            'section' => 'captcha',
            'key' => 'secret',
        ]);
        $this->assertSame('environment-secret', $settings->resolved()['captcha']['secret']);
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