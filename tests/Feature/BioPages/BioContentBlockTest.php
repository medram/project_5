<?php

namespace Tests\Feature\BioPages;

use App\Models\BioContentBlock;
use App\Models\BioPage;
use App\Models\ContentMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BioContentBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_text_social_and_embed_blocks_and_reorders_them_with_buttons(): void
    {
        $page = BioPage::factory()->create([
            'handle' => 'creator-26',
            'is_published' => true,
        ]);
        $link = $page->bioLinks()->create([
            'title' => 'Portfolio',
            'destination_url' => 'https://example.com/portfolio',
            'position' => 0,
            'is_active' => true,
        ]);

        $this->withoutVite();
        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'text',
                'text' => 'A plain-text introduction.',
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");
        $text = BioContentBlock::query()->where('type', 'text')->sole();

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'social',
                'provider' => 'instagram',
                'identifier' => 'creator.name',
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");
        $social = BioContentBlock::query()->where('type', 'social')->sole();

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'embed',
                'provider' => 'youtube',
                'identifier' => 'dQw4w9WgXcQ',
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");
        $embed = BioContentBlock::query()->where('type', 'embed')->sole();

        $this->actingAs($page->user)
            ->patch("/dashboard/biolinks/{$page->id}/content/reorder", [
                'items' => [
                    ['type' => 'block', 'id' => $embed->id],
                    ['type' => 'link', 'id' => $link->id],
                    ['type' => 'block', 'id' => $social->id],
                    ['type' => 'block', 'id' => $text->id],
                ],
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $this->assertDatabaseHas('bio_links', [
            'id' => $link->id,
            'position' => 1,
        ]);
        $this->assertDatabaseHas('bio_content_blocks', [
            'id' => $embed->id,
            'position' => 0,
        ]);
        $this->assertDatabaseHas('bio_content_blocks', [
            'id' => $social->id,
            'position' => 2,
        ]);
        $this->assertDatabaseHas('bio_content_blocks', [
            'id' => $text->id,
            'position' => 3,
        ]);
    }

    public function test_public_projection_uses_mixed_order_pinning_and_exact_utc_eligibility(): void
    {
        $now = Carbon::parse('2026-10-23 12:00:00', 'UTC');
        $this->travelTo($now);
        $page = BioPage::factory()->create([
            'handle' => 'creator-26',
            'is_published' => true,
            'appearance' => [
                'theme' => 'dark',
                'typography' => 'serif',
                'color' => 'violet',
                'background' => 'midnight',
            ],
        ]);

        $unpinnedLink = $page->bioLinks()->create([
            'title' => 'Unpinned button',
            'destination_url' => 'https://example.com/unpinned',
            'position' => 0,
            'is_active' => true,
            'starts_at' => '2026-10-23 12:00:00',
            'ends_at' => '2026-10-23 12:01:00',
        ]);
        $pinnedLink = $page->bioLinks()->create([
            'title' => 'Pinned button',
            'destination_url' => 'https://example.com/pinned',
            'position' => 1,
            'is_active' => true,
            'is_pinned' => true,
        ]);
        $pinnedText = BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'text',
            'content' => ['text' => 'Pinned text'],
            'position' => 2,
            'is_active' => true,
            'is_pinned' => true,
        ]);
        BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'text',
            'content' => ['text' => 'Expired exactly now'],
            'position' => 3,
            'is_active' => true,
            'ends_at' => '2026-10-23 12:00:00',
        ]);
        BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'text',
            'content' => ['text' => 'Starts in the future'],
            'position' => 4,
            'is_active' => true,
            'starts_at' => '2026-10-23 12:00:01',
        ]);
        BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'text',
            'content' => ['text' => 'Disabled'],
            'position' => 5,
            'is_active' => false,
        ]);

        $this->withoutVite();
        $this->get('/bio/creator-26')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('bio-pages/public', false)
                ->where('presentation.appearance.theme', 'dark')
                ->where('presentation.appearance.typography', 'serif')
                ->where('presentation.items.0.type', 'link')
                ->where('presentation.items.0.id', $pinnedLink->id)
                ->where('presentation.items.1.type', 'text')
                ->where('presentation.items.1.id', $pinnedText->id)
                ->where('presentation.items.2.id', $unpinnedLink->id)
                ->has('presentation.items', 3),
            );
    }

    public function test_image_blocks_use_private_workspace_media_and_serve_only_through_the_public_block(): void
    {
        config()->set('filesystems.disks.bio-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/bio-test'),
            'throw' => false,
        ]);
        config()->set('services.linkpilot.media.disk', 'bio-test');
        Storage::fake('bio-test');

        $page = BioPage::factory()->create([
            'handle' => 'image-creator',
            'is_published' => true,
        ]);

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'image',
                'alt_text' => 'Studio portrait',
                'image' => UploadedFile::fake()->image('portrait.png', 80, 60),
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $block = BioContentBlock::query()->sole();
        $media = ContentMedia::query()->findOrFail($block->content_media_id);
        Storage::disk('bio-test')->assertExists($media->path);
        $this->assertSame('private', $media->visibility);
        $this->assertNull($media->deleted_at);

        $this->withoutVite();
        $this->get('/bio/image-creator')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('presentation.items.0.type', 'image')
                ->where('presentation.items.0.imageUrl', "/bio/image-creator/blocks/{$block->id}/image"),
            );

        $image = $this->get("/bio/image-creator/blocks/{$block->id}/image")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotFalse(getimagesizefromstring($image->streamedContent()));
        $this->assertStringNotContainsString($media->path, $image->streamedContent());
    }

    public function test_draft_image_preview_uses_workspace_authorized_media_delivery(): void
    {
        config()->set('filesystems.disks.bio-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/bio-test'),
            'throw' => false,
        ]);
        config()->set('services.linkpilot.media.disk', 'bio-test');
        Storage::fake('bio-test');
        $this->travelTo(Carbon::parse('2026-10-24 00:00:00', 'UTC'));
        $page = BioPage::factory()->create([
            'handle' => 'draft-image-creator',
            'is_published' => false,
        ]);

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'image',
                'alt_text' => 'Draft portrait',
                'image' => UploadedFile::fake()->image('draft.png', 80, 60),
                'is_active' => true,
                'ends_at' => '2026-10-24 00:00:01',
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $block = BioContentBlock::query()->sole();
        $previewUrl = "/dashboard/biolinks/{$page->id}/blocks/{$block->id}/image";

        $this->withoutVite();
        $this->actingAs($page->user)
            ->get("/dashboard/biolinks/{$page->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('contentBlocks.0.imageUrl', $previewUrl)
                ->where('previewAt', '2026-10-24T00:00:00.000000Z'),
            );

        $this->travelTo(Carbon::parse('2026-10-24 00:00:02', 'UTC'));
        $this->actingAs($page->user)
            ->get($previewUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get("/bio/draft-image-creator/blocks/{$block->id}/image")
            ->assertNotFound();

        $page->update(['is_published' => true]);
        $this->get("/bio/draft-image-creator/blocks/{$block->id}/image")
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get($previewUrl)
            ->assertNotFound();
    }

    public function test_changing_a_non_image_block_to_an_image_requires_a_new_upload(): void
    {
        $page = BioPage::factory()->create();
        $block = $page->contentBlocks()->create([
            'type' => 'text',
            'content' => ['text' => 'Keep this until an image is uploaded.'],
            'position' => 0,
            'is_active' => true,
        ]);

        $this->withoutVite()
            ->actingAs($page->user)
            ->patch("/dashboard/biolinks/{$page->id}/blocks/{$block->id}", [
                'type' => 'image',
                'alt_text' => 'New image',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseHas('bio_content_blocks', [
            'id' => $block->id,
            'type' => 'text',
        ]);
    }

    public function test_non_image_blocks_reject_image_uploads_without_retaining_media(): void
    {
        config()->set('filesystems.disks.bio-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/bio-test'),
            'throw' => false,
        ]);
        config()->set('services.linkpilot.media.disk', 'bio-test');
        Storage::fake('bio-test');
        $page = BioPage::factory()->create();

        $this->withoutVite()
            ->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'text',
                'text' => 'This block has no image.',
                'image' => UploadedFile::fake()->image('unexpected.png', 80, 60),
                'is_active' => true,
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('bio_content_blocks', 0);
        $this->assertDatabaseCount('content_media', 0);
        $this->assertSame([], Storage::disk('bio-test')->allFiles());
    }

    public function test_untrusted_markup_provider_ids_and_remote_media_urls_are_rejected(): void
    {
        $page = BioPage::factory()->create();

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'text',
                'text' => '<script>alert(1)</script>',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('text');

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'embed',
                'provider' => 'youtube',
                'identifier' => 'https://example.com/embed/anything',
                'embed_url' => 'https://example.com/embed/anything',
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['identifier', 'embed_url']);

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'embed',
                'provider' => 'example.com',
                'identifier' => 'anything',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('provider');

        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'image',
                'image_url' => 'https://tracker.example/image.png',
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['image', 'image_url']);

        $this->assertDatabaseCount('bio_content_blocks', 0);
    }

    public function test_public_social_action_redirects_only_for_published_active_and_current_blocks(): void
    {
        $now = Carbon::parse('2026-10-23 12:00:00', 'UTC');
        $this->travelTo($now);
        $page = BioPage::factory()->create([
            'handle' => 'social-creator',
            'is_published' => true,
        ]);
        $activeBlock = BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'social',
            'content' => ['provider' => 'instagram', 'identifier' => 'creator.name'],
            'position' => 0,
            'is_active' => true,
            'starts_at' => '2026-10-23 12:00:00',
            'ends_at' => '2026-10-23 12:01:00',
        ]);
        $draftPage = BioPage::factory()->create([
            'handle' => 'draft-creator',
            'is_published' => false,
        ]);
        $draftBlock = BioContentBlock::query()->create([
            'bio_page_id' => $draftPage->id,
            'type' => 'social',
            'content' => ['provider' => 'instagram', 'identifier' => 'draft.person'],
            'position' => 0,
            'is_active' => true,
        ]);
        $inactiveBlock = BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'social',
            'content' => ['provider' => 'instagram', 'identifier' => 'inactive.person'],
            'position' => 1,
            'is_active' => false,
        ]);
        $futureBlock = BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'social',
            'content' => ['provider' => 'instagram', 'identifier' => 'future.person'],
            'position' => 2,
            'is_active' => true,
            'starts_at' => '2026-10-23 12:00:01',
        ]);
        $foreignPage = BioPage::factory()->create([
            'handle' => 'foreign-creator',
            'is_published' => true,
        ]);
        $foreignBlock = BioContentBlock::query()->create([
            'bio_page_id' => $foreignPage->id,
            'type' => 'social',
            'content' => ['provider' => 'instagram', 'identifier' => 'foreign.person'],
            'position' => 0,
            'is_active' => true,
        ]);

        $this->get("/bio/social-creator/blocks/{$activeBlock->id}")
            ->assertRedirect('https://instagram.com/creator.name/');
        $this->get("/bio/draft-creator/blocks/{$draftBlock->id}")->assertNotFound();
        $this->get("/bio/social-creator/blocks/{$inactiveBlock->id}")->assertNotFound();
        $this->get("/bio/social-creator/blocks/{$futureBlock->id}")->assertNotFound();
        $this->get("/bio/social-creator/blocks/{$foreignBlock->id}")->assertNotFound();
    }

    public function test_owner_cannot_mutate_a_block_from_another_workspace(): void
    {
        $page = BioPage::factory()->create();
        $otherPage = BioPage::factory()->create();
        $block = BioContentBlock::query()->create([
            'bio_page_id' => $otherPage->id,
            'type' => 'text',
            'content' => ['text' => 'Private content'],
            'position' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($page->user)
            ->patch("/dashboard/biolinks/{$otherPage->id}/blocks/{$block->id}", [
                'type' => 'text',
                'text' => 'Tampered content',
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('bio_content_blocks', [
            'id' => $block->id,
            'content->text' => 'Private content',
        ]);
    }

    public function test_owner_updates_and_deletes_content_blocks_and_rejects_invalid_appearance(): void
    {
        $page = BioPage::factory()->create();
        $block = BioContentBlock::query()->create([
            'bio_page_id' => $page->id,
            'type' => 'text',
            'content' => ['text' => 'Initial copy'],
            'position' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($page->user)
            ->patch("/dashboard/biolinks/{$page->id}/blocks/{$block->id}", [
                'type' => 'text',
                'text' => 'Updated copy',
                'is_active' => true,
                'is_pinned' => true,
                'starts_at' => '2026-10-23T12:00:00Z',
                'ends_at' => '2026-10-23T13:00:00Z',
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $this->assertDatabaseHas('bio_content_blocks', [
            'id' => $block->id,
            'content->text' => 'Updated copy',
            'is_pinned' => true,
        ]);

        $this->actingAs($page->user)
            ->patch("/dashboard/biolinks/{$page->id}", [
                'handle' => $page->handle,
                'display_name' => $page->display_name,
                'bio' => $page->bio,
                'is_published' => false,
                'appearance' => [
                    'theme' => 'dark; background-image:url(https://attacker.test)',
                    'typography' => 'serif',
                    'color' => 'violet',
                    'background' => 'midnight',
                ],
            ])
            ->assertSessionHasErrors('appearance.theme');

        $this->actingAs($page->user)
            ->delete("/dashboard/biolinks/{$page->id}/blocks/{$block->id}")
            ->assertRedirect("/dashboard/biolinks/{$page->id}");
        $this->assertDatabaseMissing('bio_content_blocks', ['id' => $block->id]);
    }

    public function test_public_button_action_observes_inclusive_start_and_exclusive_end_schedule(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00', 'UTC'));
        $page = BioPage::factory()->create([
            'handle' => 'scheduled-creator',
            'is_published' => true,
        ]);
        $link = $page->bioLinks()->create([
            'title' => 'Scheduled',
            'destination_url' => 'https://example.com/scheduled',
            'position' => 0,
            'is_active' => true,
            'starts_at' => '2026-10-23 12:00:00',
            'ends_at' => '2026-10-23 12:01:00',
        ]);

        $this->get("/bio/scheduled-creator/links/{$link->id}")
            ->assertRedirect('https://example.com/scheduled');
        $this->travelTo(Carbon::parse('2026-10-23 12:01:00', 'UTC'));
        $this->get("/bio/scheduled-creator/links/{$link->id}")->assertNotFound();
    }

    public function test_image_delivery_rechecks_publication_and_media_expiry(): void
    {
        config()->set('filesystems.disks.bio-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/bio-test'),
            'throw' => false,
        ]);
        config()->set('services.linkpilot.media.disk', 'bio-test');
        Storage::fake('bio-test');

        $page = BioPage::factory()->create([
            'handle' => 'image-expiry',
            'is_published' => true,
        ]);
        $this->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/blocks", [
                'type' => 'image',
                'alt_text' => 'Portrait',
                'image' => UploadedFile::fake()->image('portrait.png', 80, 60),
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");
        $block = BioContentBlock::query()->sole();
        $media = ContentMedia::query()->findOrFail($block->content_media_id);

        $page->update(['is_published' => false]);
        $this->get("/bio/image-expiry/blocks/{$block->id}/image")->assertNotFound();

        $page->update(['is_published' => true]);
        $media->forceFill(['expires_at' => now('UTC')->subSecond()])->save();
        $this->get("/bio/image-expiry/blocks/{$block->id}/image")->assertNotFound();

        $media->forceFill(['expires_at' => now('UTC')->addDay()])->save();
        $media->delete();
        $this->get("/bio/image-expiry/blocks/{$block->id}/image")->assertNotFound();
    }

    public function test_all_six_embed_providers_accept_canonical_ids_only(): void
    {
        $page = BioPage::factory()->create();
        $providerIds = [
            ['provider' => 'youtube', 'identifier' => 'dQw4w9WgXcQ'],
            ['provider' => 'soundcloud', 'identifier' => '123456789'],
            ['provider' => 'vimeo', 'identifier' => '1234567'],
            ['provider' => 'spotify', 'identifier' => '0123456789012345678901', 'provider_type' => 'track'],
            ['provider' => 'twitch', 'identifier' => 'linkpilot'],
            ['provider' => 'tiktok', 'identifier' => '1234567890123456789'],
        ];

        foreach ($providerIds as $embed) {
            $this->actingAs($page->user)
                ->post("/dashboard/biolinks/{$page->id}/blocks", [
                    'type' => 'embed',
                    ...$embed,
                    'is_active' => true,
                ])
                ->assertRedirect("/dashboard/biolinks/{$page->id}");
        }

        $this->assertDatabaseCount('bio_content_blocks', 6);
    }
}
