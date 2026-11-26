<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\CustomPage;
use App\Models\Locale;
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

    public function test_selection_and_clear_soft_delete_assets_only_when_unreferenced(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $storage = app(ContentMediaStorage::class);
        $first = $storage->storePlatformAsset(UploadedFile::fake()->image('first.png', 10, 10), $administrator);
        $settings->updateSection($administrator, 'general', [
            'logo_light_id' => $first->id,
            'logo_dark_id' => $first->id,
        ], $settings->version('general'));
        $second = $storage->storePlatformAsset(UploadedFile::fake()->image('second.png', 10, 10), $administrator);

        $this->actingAs($administrator)
            ->patch('/admin/settings/general', [
                'version' => 1,
                'values' => ['logo_light_id' => $second->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertFalse($first->refresh()->trashed());

        $this->actingAs($administrator)
            ->patch('/admin/settings/general', [
                'version' => 2,
                'values' => ['logo_dark_id' => null],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSoftDeleted('content_media', ['id' => $first->id]);

        $this->actingAs($administrator)
            ->patch('/admin/settings/general', [
                'version' => 3,
                'values' => ['logo_light_id' => null],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSoftDeleted('content_media', ['id' => $second->id]);
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

    public function test_platform_asset_options_exclude_tenant_media(): void
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

    public function test_selected_platform_asset_is_not_dropped_by_option_result_limit(): void
    {
        $administrator = User::factory()->admin()->create();
        $selectedId = 0;

        for ($index = 0; $index < 51; $index++) {
            $publicId = (string) Str::uuid();
            $asset = ContentMedia::query()->create([
                'uploaded_by' => $administrator->id,
                'public_id' => $publicId,
                'disk' => self::DISK,
                'path' => 'platform/'.$publicId.'.png',
                'original_name' => "asset-{$index}.png",
                'mime_type' => 'image/png',
                'byte_size' => 10,
                'sha256' => hash('sha256', (string) $index),
                'visibility' => 'private',
                'scan_state' => 'not_required',
                'purpose' => MediaPurpose::PlatformAsset,
            ]);

            if ($index === 0) {
                $selectedId = (int) $asset->getKey();
            }
        }

        $response = $this->actingAs($administrator)
            ->getJson('/admin/settings/options/platform-assets?selected[]='.$selectedId)
            ->assertOk();
        $values = array_column($response->json('items'), 'value');

        $this->assertCount(50, $values);
        $this->assertContains((string) $selectedId, $values);
    }

    public function test_page_and_locale_options_filter_unpublished_records_and_keep_selected_values(): void
    {
        $administrator = User::factory()->admin()->create();
        $privacyPage = CustomPage::query()->create([
            'slug' => 'privacy-page',
            'title' => 'Privacy',
            'body' => '<p>Privacy</p>',
            'state' => 'published',
            'created_by' => $administrator->id,
        ]);
        $termsPage = CustomPage::query()->create([
            'slug' => 'terms-page',
            'title' => 'Terms',
            'body' => '<p>Terms</p>',
            'state' => 'published',
            'created_by' => $administrator->id,
        ]);
        $draftPage = CustomPage::query()->create([
            'slug' => 'draft-page',
            'title' => 'Draft',
            'body' => '<p>Draft</p>',
            'state' => 'draft',
            'created_by' => $administrator->id,
        ]);

        $pages = $this->actingAs($administrator)
            ->getJson('/admin/settings/options/published-pages?q=Privacy&selected[]='.$termsPage->id.'&selected[]='.$draftPage->id)
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            [(string) $privacyPage->id, (string) $termsPage->id],
            array_column($pages->json('items'), 'value'),
        );

        Locale::query()->create(['code' => 'es', 'name' => 'Spanish', 'direction' => 'ltr', 'is_published' => true]);
        Locale::query()->create(['code' => 'de', 'name' => 'German', 'direction' => 'ltr', 'is_published' => true]);
        Locale::query()->create(['code' => 'fr', 'name' => 'French', 'direction' => 'ltr', 'is_published' => false]);

        $locales = $this->getJson('/admin/settings/options/locales?q=Spanish&selected[]=de&selected[]=fr')
            ->assertOk();
        $this->assertEqualsCanonicalizing(['es', 'de'], array_column($locales->json('items'), 'value'));
    }

    public function test_timezone_and_storage_disk_options_keep_valid_selected_values(): void
    {
        $administrator = User::factory()->admin()->create();
        $timezones = $this->actingAs($administrator)
            ->getJson('/admin/settings/options/timezones?q=America%2FLos_Angeles&selected[]=UTC')
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            ['America/Los_Angeles', 'UTC'],
            array_column($timezones->json('items'), 'value'),
        );

        config()->set('filesystems.disks', [
            'private-local' => ['driver' => 'local', 'root' => storage_path('framework/private-settings')],
            'public-local' => ['driver' => 'local', 'root' => public_path('settings'), 'visibility' => 'public'],
            'private-s3' => ['driver' => 's3', 'visibility' => 'private'],
            'unsupported-ftp' => ['driver' => 'ftp', 'visibility' => 'private'],
        ]);
        $disks = $this->getJson('/admin/settings/options/storage-disks?q=private-local&selected[]=private-s3')
            ->assertOk();
        $this->assertEqualsCanonicalizing(
            ['private-local', 'private-s3'],
            array_column($disks->json('items'), 'value'),
        );
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

    public function test_storage_check_writes_and_removes_a_probe_file(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'uploading', [
            'disk' => self::DISK,
        ], $settings->version('uploading'));

        $this->actingAs($administrator)
            ->from('/admin/settings/uploading')
            ->post('/admin/settings/uploading/test')
            ->assertRedirect('/admin/settings/uploading')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Storage disk accepted a test write.');

        $this->assertSame([], Storage::disk(self::DISK)->allFiles('healthcheck'));
    }

    public function test_storage_check_reports_an_unwritable_disk(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'uploading', [
            'disk' => self::DISK,
        ], $settings->version('uploading'));

        $root = (string) tempnam(sys_get_temp_dir(), 'linkpilot-storage');

        try {
            config()->set('filesystems.disks.'.self::DISK.'.root', $root.'/media');
            Storage::forgetDisk(self::DISK);

            $this->actingAs($administrator)
                ->post('/admin/settings/uploading/test')
                ->assertSessionHasErrors('storage');
        } finally {
            @unlink($root);
        }
    }

    public function test_storage_check_requires_the_manage_permission(): void
    {
        $this->actingAs(User::factory()->support()->create())
            ->post('/admin/settings/uploading/test')
            ->assertForbidden();
    }

    public function test_stripe_validation_stops_when_billing_is_disabled(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'subscriptions', [
            'billing_enabled' => false,
        ], $settings->version('subscriptions'));

        $this->actingAs($administrator)
            ->from('/admin/settings/payment-gateways')
            ->post('/admin/settings/payment-gateways/test')
            ->assertRedirect('/admin/settings/payment-gateways')
            ->assertSessionHasErrors(['stripe' => 'Billing is disabled.']);
    }
}
