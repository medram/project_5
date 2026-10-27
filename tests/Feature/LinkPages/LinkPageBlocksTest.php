<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkPageBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.link-page-test', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/link-page-test'),
            'throw' => false,
        ]);
        config()->set('services.linkpilot.media.disk', 'link-page-test');
        Storage::fake('link-page-test');
    }

    public function test_public_page_projects_ordered_text_and_image_blocks_and_omits_inactive_or_future_content(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $page = $this->createPage($owner, ['is_published' => true]);
        $button = $this->createItem($owner, $page);
        $text = $this->createBlock($owner, $page, [
            'type' => 'text',
            'text' => 'A plain-text campaign introduction.',
        ]);
        $image = $this->createBlock($owner, $page, [
            'type' => 'image',
            'alt_text' => 'Product launch artwork',
            'image' => UploadedFile::fake()->image('launch.png', 80, 60),
        ]);
        $hidden = $this->createBlock($owner, $page, [
            'type' => 'text',
            'text' => 'Hidden text.',
            'is_active' => false,
        ]);
        $future = $this->createBlock($owner, $page, [
            'type' => 'text',
            'text' => 'Future text.',
            'starts_at' => '2026-10-23 12:00:01',
        ]);

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/items/reorder", [
                'items' => [
                    ['type' => 'block', 'id' => $text],
                    ['type' => 'link', 'id' => $button->id],
                    ['type' => 'block', 'id' => $image],
                    ['type' => 'block', 'id' => $hidden],
                    ['type' => 'block', 'id' => $future],
                ],
            ])
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

        $mediaId = DB::table('link_page_blocks')->where('id', $image)->value('content_media_id');
        $media = DB::table('content_media')->where('id', $mediaId)->first();
        $this->assertNotNull($media);
        Storage::disk('link-page-test')->assertExists($media->path);
        $this->withoutVite();
        $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('link-pages/public', false)
                ->where('items.0.type', 'text')
                ->where('items.0.id', $text)
                ->where('items.0.text', 'A plain-text campaign introduction.')
                ->where('items.1.type', 'link')
                ->where('items.1.id', $button->id)
                ->where('items.1.url', "/pages/{$page->slug}/items/{$button->id}")
                ->where('items.2.type', 'image')
                ->where('items.2.id', $image)
                ->where('items.2.altText', 'Product launch artwork')
                ->where('items.2.imageUrl', "/content-media/{$media->public_id}")
                ->has('items', 3),
            );

        $this->get("/content-media/{$media->public_id}")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get("/pages/{$page->slug}/items/{$button->id}")
            ->assertRedirect('https://example.com/start');
    }

    public function test_template_changes_preserve_page_content_and_only_allow_the_three_supported_templates(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner, ['is_published' => true]);
        $button = $this->createItem($owner, $page);
        $text = $this->createBlock($owner, $page, [
            'type' => 'text',
            'text' => 'Keep this content when the template changes.',
        ]);

        foreach (['profile', 'campaign', 'standard'] as $template) {
            $this->actingAs($owner)
                ->patch("/dashboard/link-pages/{$page->id}", $this->pagePayload([
                    'name' => $page->name,
                    'slug' => $page->slug,
                    'title' => $page->title,
                    'description' => $page->description,
                    'template' => $template,
                ]))
                ->assertRedirect("/dashboard/link-pages/{$page->id}");

            $this->assertSame(
                $template,
                DB::table('link_pages')->where('id', $page->id)->value('template'),
            );
            $this->assertDatabaseHas('link_page_items', [
                'id' => $button->id,
                'destination_url' => 'https://example.com/start',
            ]);
            $this->assertSame(
                'Keep this content when the template changes.',
                json_decode(DB::table('link_page_blocks')->where('id', $text)->value('content'), true)['text'],
            );

            $this->withoutVite();
            $this->get("/pages/{$page->slug}")
                ->assertOk()
                ->assertInertia(fn (Assert $response) => $response->where('template', $template));
        }

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}", $this->pagePayload([
                'name' => $page->name,
                'slug' => $page->slug,
                'title' => $page->title,
                'description' => $page->description,
                'template' => 'custom',
            ]))
            ->assertSessionHasErrors('template');
    }

    public function test_block_mutations_are_scoped_to_the_parent_page_and_current_workspace(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $page = LinkPage::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $owner->currentWorkspace->id,
        ]);
        $siblingPage = LinkPage::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $owner->currentWorkspace->id,
        ]);
        $foreignPage = LinkPage::factory()->create([
            'user_id' => $otherUser->id,
            'workspace_id' => $otherUser->currentWorkspace->id,
        ]);

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$foreignPage->id}/blocks", [
                'type' => 'text',
                'text' => 'Must not cross workspaces.',
                'is_active' => true,
            ])
            ->assertNotFound();

        $block = $this->createBlock($owner, $page, [
            'type' => 'text',
            'text' => 'Parent-scoped content.',
        ]);

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$siblingPage->id}/blocks/{$block}", [
                'type' => 'text',
                'text' => 'Wrong parent.',
                'is_active' => true,
            ])
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->delete("/dashboard/link-pages/{$page->id}/blocks/{$block}")
            ->assertNotFound();

        $this->assertSame(1, DB::table('link_page_blocks')->where('id', $block)->count());
    }

    public function test_text_blocks_reject_markup_and_client_supplied_media_locations(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'text',
                'text' => '<script>alert(1)</script>',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('text');

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'text',
                'text' => 'Safe text',
                'content_media_id' => 999,
                'disk' => 'public',
                'path' => 'untrusted.jpg',
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['content_media_id', 'disk', 'path']);

        $this->assertDatabaseCount('link_page_blocks', 0);
    }

    public function test_image_storage_rejection_is_reported_and_leaves_no_block_or_media(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);

        ob_start();
        $image = imagecreatetruecolor(2, 2);
        imagepng($image);
        imagedestroy($image);
        $png = (string) ob_get_clean();

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'image',
                'alt_text' => 'Mismatched extension',
                'image' => UploadedFile::fake()->createWithContent('image.jpg', $png),
                'is_active' => true,
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('link_page_blocks', 0);
        $this->assertDatabaseCount('content_media', 0);
        $this->assertSame([], Storage::disk('link-page-test')->allFiles());
    }

    public function test_deleting_a_page_soft_deletes_its_block_media(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $blockId = $this->createBlock($owner, $page, [
            'type' => 'image',
            'alt_text' => 'Page artwork',
            'image' => UploadedFile::fake()->image('page.png', 80, 60),
        ]);
        $mediaId = DB::table('link_page_blocks')->where('id', $blockId)->value('content_media_id');
        $mediaPath = DB::table('content_media')->where('id', $mediaId)->value('path');

        $this->actingAs($owner)
            ->delete("/dashboard/link-pages/{$page->id}")
            ->assertRedirect('/dashboard/link-pages');

        $this->assertDatabaseMissing('link_pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('link_page_blocks', ['id' => $blockId]);
        $this->assertSoftDeleted('content_media', ['id' => $mediaId]);
        Storage::disk('link-page-test')->assertExists($mediaPath);
    }

    /** @param array<string, mixed> $overrides */
    private function pagePayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Launch page',
            'slug' => 'launch-'.Str::lower(Str::random(6)),
            'title' => 'Campaign launch',
            'description' => 'A page for the launch.',
            'is_published' => true,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function createPage(User $user, array $overrides = []): LinkPage
    {
        $payload = $this->pagePayload($overrides);
        $this->actingAs($user)
            ->post('/dashboard/link-pages', $payload)
            ->assertRedirect();

        return LinkPage::query()->where('slug', $payload['slug'])->sole();
    }

    /** @param array<string, mixed> $overrides */
    private function createItem(User $user, LinkPage $page, array $overrides = []): LinkPageItem
    {
        $this->actingAs($user)
            ->post("/dashboard/link-pages/{$page->id}/items", array_replace([
                'title' => 'Start here',
                'destination_url' => 'https://example.com/start',
                'is_active' => true,
            ], $overrides))
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

        return LinkPageItem::query()->where('link_page_id', $page->id)->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function createBlock(User $user, LinkPage $page, array $overrides = []): int
    {
        $payload = array_replace([
            'type' => 'text',
            'is_active' => true,
        ], $overrides);
        if ($payload['type'] === 'text' && ! isset($payload['text'])) {
            $payload['text'] = 'A content block.';
        }

        $this->actingAs($user)
            ->post("/dashboard/link-pages/{$page->id}/blocks", $payload)
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

        return (int) DB::table('link_page_blocks')
            ->where('link_page_id', $page->id)
            ->orderByDesc('id')
            ->value('id');
    }
}
