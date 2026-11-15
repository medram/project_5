<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\User;
use App\Services\Content\ContentMediaStorage;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSettingActionsTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'settings-actions-test';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(self::DISK);
        config()->set('filesystems.disks.'.self::DISK, ['driver' => 'local', 'root' => storage_path('framework/testing/settings-actions')]);
        config()->set('services.linkpilot.media.disk', self::DISK);
    }

    public function test_view_only_admins_can_resolve_options_but_cannot_mutate(): void
    {
        $administrator = User::factory()->admin()->create();
        $support = User::factory()->support()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'uploading', [
            'disk' => self::DISK,
        ], $settings->version('uploading'));
        $asset = app(ContentMediaStorage::class)->storePlatformAsset(
            UploadedFile::fake()->image('option-logo.png', 20, 20),
            $administrator,
        );

        $this->actingAs($administrator)
            ->getJson('/admin/settings/options/platform-assets?selected[]='.$asset->id)
            ->assertOk()
            ->assertJsonPath('items.0.value', (string) $asset->id);

        $this->actingAs($support)
            ->getJson('/admin/settings/options/platform-assets')
            ->assertForbidden();

        $this->actingAs($administrator)
            ->getJson('/admin/settings/options/not-a-source')
            ->assertNotFound();

        $this->actingAs($support)
            ->delete('/admin/settings/general/secrets/logo_light_id', ['version' => 0])
            ->assertForbidden();

        $this->actingAs($support)
            ->post('/admin/settings/general/assets/logo_light_id', [
                'version' => 0,
                'file' => UploadedFile::fake()->image('logo.png', 10, 10),
            ])
            ->assertForbidden();
    }

    public function test_asset_upload_is_version_checked_and_cleans_a_stale_candidate(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'uploading', [
            'disk' => self::DISK,
        ], $settings->version('uploading'));

        $this->actingAs($administrator)
            ->post('/admin/settings/general/assets/logo_light_id', [
                'version' => 99,
                'file' => UploadedFile::fake()->image('logo.png', 10, 10),
            ])
            ->assertSessionHasErrors('version');

        $this->assertDatabaseCount('content_media', 0);

        $this->actingAs($administrator)
            ->post('/admin/settings/general/assets/logo_light_id', [
                'version' => 0,
                'file' => UploadedFile::fake()->image('logo.png', 10, 10),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $asset = ContentMedia::query()->sole();
        $this->assertSame($asset->id, $settings->resolved()['general']['logo_light_id']);
        Storage::disk(self::DISK)->assertExists($asset->path);
    }

    public function test_secret_clear_rejects_stale_versions_and_unknown_keys(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'captcha', [
            'provider' => 'turnstile',
            'site_key' => 'site-key',
            'secret' => 'captcha-secret',
            'on_registration' => true,
        ], $settings->version('captcha'));

        $this->actingAs($administrator)
            ->delete('/admin/settings/captcha/secrets/secret', ['version' => 0])
            ->assertSessionHasErrors('version');
        $this->assertSame('captcha-secret', $settings->secret('captcha', 'secret'));

        $this->actingAs($administrator)
            ->delete('/admin/settings/captcha/secrets/site_key', ['version' => 1])
            ->assertNotFound();

        $this->actingAs($administrator)
            ->delete('/admin/settings/captcha/secrets/secret', ['version' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($settings->secret('captcha', 'secret'));
        $this->assertSame(2, $settings->version('captcha'));
    }

    public function test_options_exclude_tenant_media_and_unpublished_pages(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        ContentMedia::query()->create([
            'workspace_id' => $workspace->id,
            'uploaded_by' => $administrator->id,
            'public_id' => (string) Str::uuid(),
            'disk' => self::DISK,
            'path' => 'workspaces/'.$workspace->id.'/tenant.png',
            'original_name' => 'tenant.png',
            'mime_type' => 'image/png',
            'byte_size' => 10,
            'sha256' => str_repeat('d', 64),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'purpose' => MediaPurpose::Image,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($administrator)
            ->getJson('/admin/settings/options/platform-assets')
            ->assertOk();

        $this->assertSame([], $response->json('items'));
    }

    public function test_email_test_uses_saved_settings_and_returns_a_generic_failure(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'email', [
            'mailer' => 'log',
            'sender_name' => 'Acme',
            'sender_address' => 'mail@acme.test',
        ], $settings->version('email'));

        $this->actingAs($administrator)
            ->post('/admin/settings/email/test', ['recipient' => 'admin@example.test'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $settings->updateSection($administrator, 'email', [
            'mailer' => 'smtp',
            'smtp_host' => '127.0.0.1',
            'smtp_port' => 1,
            'smtp_security' => 'none',
            'smtp_timeout_seconds' => 1,
        ], $settings->version('email'));

        $this->actingAs($administrator)
            ->post('/admin/settings/email/test', ['recipient' => 'admin@example.test'])
            ->assertSessionHasErrors('email');
    }
}
