<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\CustomPage;
use App\Models\User;
use App\Services\Content\ContentMediaStorage;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomPageSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('filesystems.disks.seo-test', ['driver' => 'local', 'root' => storage_path('framework/testing/seo-assets')]);
        Storage::fake('seo-test');
        config()->set('services.linkpilot.media.disk', 'seo-test');
        $this->withoutVite();
    }

    public function test_published_page_emits_social_metadata_and_exposes_only_eligible_published_assets(): void
    {
        $admin = User::factory()->admin()->create();
        $image = $this->asset($admin, 'published.png');
        $draftImage = $this->asset($admin, 'draft.png');
        $tenant = $this->tenantAsset($admin);
        $this->page('published-page', 'published', $image->id, ['seo_title' => 'Specific title', 'seo_description' => 'Specific description']);
        $this->page('draft-page', 'draft', $draftImage->id);
        $this->page('tenant-page', 'published', $tenant->id);
        $url = route('platform.media', ['media' => $image->id]);

        $this->get('/content/published-page')->assertOk()
            ->assertSee('<meta property="og:title" content="Specific title">', false)
            ->assertSee('<meta property="og:description" content="Specific description">', false)
            ->assertSee('<meta name="twitter:description" content="Specific description">', false)
            ->assertSee('<meta property="og:image" content="'.$url.'">', false)
            ->assertSee('<meta name="twitter:image" content="'.$url.'">', false);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png');
        $this->get(route('platform.media', ['media' => $draftImage->id]))->assertNotFound();
        $this->get(route('platform.media', ['media' => $tenant->id]))->assertNotFound();
        $this->get('/content/draft-page')->assertNotFound();
    }

    public function test_missing_or_ineligible_page_metadata_falls_back_to_platform_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $fallback = $this->asset($admin, 'fallback.png');
        $tenant = $this->tenantAsset($admin);
        $settings = app(PlatformSettings::class);
        $settings->updateSection($admin, 'seo', [
            'default_title' => 'Platform title',
            'default_description' => 'Platform description',
            'og_media_id' => $fallback->id,
        ], $settings->version('seo'));
        $this->page('fallback-page', 'published', $tenant->id);

        $this->get('/content/fallback-page')->assertOk()
            ->assertSee('<meta property="og:title" content="Platform title">', false)
            ->assertSee('<meta property="og:description" content="Platform description">', false)
            ->assertSee('<meta property="og:image" content="'.route('platform.media', ['media' => $fallback->id]).'">', false)
            ->assertDontSee(route('platform.media', ['media' => $tenant->id]));
    }

    public function test_editor_rejects_ineligible_and_deleted_images(): void
    {
        $admin = User::factory()->admin()->create();
        $tenant = $this->tenantAsset($admin);
        $expired = $this->asset($admin, 'expired.png');
        $expired->update(['expires_at' => now()->addDay()]);
        $deleted = $this->asset($admin, 'deleted.png');
        $deleted->delete();
        $invalidMime = $this->asset($admin, 'invalid.png');
        $invalidMime->update(['mime_type' => 'application/pdf']);
        $unscanned = $this->asset($admin, 'unscanned.png');
        $unscanned->update(['scan_state' => 'pending']);
        foreach ([$tenant, $expired, $deleted, $invalidMime, $unscanned] as $media) {
            $this->actingAs($admin)->post('/admin/custom-pages', [
                'slug' => 'test-page', 'title' => 'Test', 'body' => '<p>Body</p>',
                'state' => 'draft', 'og_media_id' => $media->id,
            ])->assertSessionHasErrors('og_media_id');
        }
        $this->assertDatabaseMissing('custom_pages', ['slug' => 'test-page']);
    }

    public function test_asset_options_are_searchable_and_require_page_management(): void
    {
        $admin = User::factory()->admin()->create();
        $media = $this->asset($admin, 'unique-hero.png');
        $other = $this->asset($admin, 'other.png');
        $tenant = $this->tenantAsset($admin);
        $this->actingAs($admin)->getJson('/admin/custom-pages/assets/options?q=unique-hero')
            ->assertOk()->assertJsonFragment(['value' => (string) $media->id])
            ->assertJsonMissing(['value' => (string) $other->id])
            ->assertJsonMissing(['value' => (string) $tenant->id]);
        $this->actingAs(User::factory()->support()->create())
            ->getJson('/admin/custom-pages/assets/options')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/custom-pages/assets/options')->assertForbidden();
    }

    public function test_page_og_asset_is_not_collected_when_platform_default_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $old = $this->asset($admin, 'page-og.png');
        $new = $this->asset($admin, 'new-default.png');
        $settings = app(PlatformSettings::class);
        $settings->updateAsset($admin, 'seo', 'og_media_id', $old->id, $settings->version('seo'));
        $this->page('og-reference', 'published', $old->id);
        $settings->updateAsset($admin, 'seo', 'og_media_id', $new->id, $settings->version('seo'));
        app(ContentMediaStorage::class)->deleteIfUnreferencedPlatformAsset($old->id);

        $this->assertNotSoftDeleted('content_media', ['id' => $old->id]);
        $this->get(route('platform.media', ['media' => $old->id]))->assertOk();
    }

    private function asset(User $admin, string $name): ContentMedia
    {
        return app(ContentMediaStorage::class)->storePlatformAsset(UploadedFile::fake()->image($name, 20, 20), $admin);
    }

    private function tenantAsset(User $admin): ContentMedia
    {
        Storage::disk('seo-test')->put('tenant.png', 'tenant image bytes');
        return ContentMedia::query()->create([
            'workspace_id' => $admin->personalWorkspace()->firstOrFail()->id,
            'uploaded_by' => $admin->id, 'public_id' => (string) Str::uuid(),
            'disk' => 'seo-test', 'path' => 'tenant.png', 'original_name' => 'tenant.png',
            'mime_type' => 'image/png', 'byte_size' => 10, 'sha256' => str_repeat('a', 64),
            'visibility' => 'private', 'scan_state' => 'clean', 'purpose' => MediaPurpose::Image,
            'expires_at' => null,
        ]);
    }

    private function page(string $slug, string $state, int $mediaId, array $attributes = []): CustomPage
    {
        return CustomPage::query()->create(array_merge([
            'slug' => $slug, 'title' => 'Page', 'body' => '<p>Body</p>', 'state' => $state,
            'published_at' => $state === 'published' ? now() : null, 'og_media_id' => $mediaId,
        ], $attributes));
    }
}
