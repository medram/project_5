<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\User;
use App\Services\Content\ContentMediaStorage;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAssetTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'platform-asset-test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.'.self::DISK, ['driver' => 'local', 'root' => storage_path('framework/testing/platform-assets')]);
        Storage::fake(self::DISK);
        config()->set('services.linkpilot.media.disk', self::DISK);
    }

    public function test_platform_images_are_workspace_free_private_and_retained(): void
    {
        $administrator = User::factory()->admin()->create();
        $media = app(ContentMediaStorage::class)->storePlatformAsset(
            UploadedFile::fake()->image('platform-logo.png', 40, 20),
            $administrator,
        );

        $this->assertNull($media->workspace_id);
        $this->assertSame($administrator->id, $media->uploaded_by);
        $this->assertSame(MediaPurpose::PlatformAsset, $media->purpose);
        $this->assertSame('private', $media->visibility);
        $this->assertNull($media->expires_at);
        $this->assertStringStartsWith('platform-assets/', $media->path);
        $this->assertFalse($media->trashed());
        Storage::disk(self::DISK)->assertExists($media->path);
        $this->assertTrue($media->purpose->retainsIndefinitely());

        Artisan::call('linkpilot:prune-content-media');
        $this->assertDatabaseHas('content_media', ['id' => $media->id]);
        Storage::disk(self::DISK)->assertExists($media->path);
    }

    public function test_settings_reject_tenant_media_and_accept_platform_assets_only(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $tenantMedia = ContentMedia::query()->create([
            'workspace_id' => $workspace->id,
            'uploaded_by' => $administrator->id,
            'public_id' => (string) Str::uuid(),
            'disk' => self::DISK,
            'path' => 'workspaces/'.$workspace->id.'/content-media/tenant.png',
            'original_name' => 'tenant.png',
            'mime_type' => 'image/png',
            'byte_size' => 100,
            'sha256' => str_repeat('b', 64),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'purpose' => MediaPurpose::Image,
            'expires_at' => now()->addDays(30),
        ]);
        $platformMedia = app(ContentMediaStorage::class)->storePlatformAsset(
            UploadedFile::fake()->image('platform.png', 20, 20),
            $administrator,
        );
        $settings = app(PlatformSettings::class);

        $this->actingAs($administrator)
            ->patch('/admin/settings/general', [
                'version' => 0,
                'values' => ['logo_light_id' => $tenantMedia->id],
            ])
            ->assertSessionHasErrors('values.logo_light_id');

        $this->actingAs($administrator)
            ->patch('/admin/settings/general', [
                'version' => 0,
                'values' => ['logo_light_id' => $platformMedia->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($platformMedia->id, $settings->resolved()['general']['logo_light_id']);
    }

    public function test_platform_media_is_served_only_while_referenced_by_platform_settings(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $media = app(ContentMediaStorage::class)->storePlatformAsset(
            UploadedFile::fake()->image('logo.png', 20, 20),
            $administrator,
        );

        $this->get(route('platform.media', ['media' => $media->id]))->assertNotFound();

        $settings->updateSection($administrator, 'general', [
            'logo_light_id' => $media->id,
        ], $settings->version('general'));

        $this->get(route('platform.media', ['media' => $media->id]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $settings->updateSection($administrator, 'general', [
            'logo_light_id' => null,
        ], $settings->version('general'));

        $this->get(route('platform.media', ['media' => $media->id]))->assertNotFound();
    }
}
