<?php

namespace Tests\Feature\Content;

use App\Models\ContentMedia;
use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LinkPageMediaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'link-page-media-test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.'.self::DISK, [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/'.self::DISK),
            'throw' => false,
        ]);
        config()->set('services.linkpilot.media.disk', self::DISK);
        config()->set('services.linkpilot.media.retention_days', 365);
        Storage::fake(self::DISK);
    }

    public function test_published_image_is_delivered_inline_with_its_detected_mime(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $blockId = $this->createImageBlock($owner, $page);
        $media = $this->mediaForBlock($blockId);
        $stored = Storage::disk(self::DISK)->get($media->path);

        $response = $this->get('/content-media/'.$media->public_id)
            ->assertOk()
            ->assertHeader('Content-Type', $media->mime_type)
            ->assertHeader('Content-Disposition', 'inline')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertEqualsCanonicalizing(
            ['private', 'no-store'],
            array_map('trim', explode(',', (string) $response->headers->get('Cache-Control'))),
        );
        $response->assertStreamedContent($stored);
    }

    public function test_unreadable_media_is_not_reported_as_a_successful_stream(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $blockId = $this->createImageBlock($owner, $page);
        $media = $this->mediaForBlock($blockId);
        $disk = \Mockery::mock();
        $disk->shouldReceive('exists')->once()->with($media->path)->andReturnTrue();
        $disk->shouldReceive('readStream')
            ->once()
            ->with($media->path)
            ->andThrow(UnableToReadFile::fromLocation($media->path));

        Storage::shouldReceive('disk')->once()->with(self::DISK)->andReturn($disk);

        $this->get('/content-media/'.$media->public_id)->assertNotFound();
    }

    public function test_eligible_pdf_download_is_attachment_only_with_a_sanitized_filename(): void
    {
        if (! $this->clamavAvailable()) {
            $this->markTestSkipped('A real ClamAV executable is required to verify successful PDF delivery.');
        }

        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'file',
                'is_active' => true,
                'file' => UploadedFile::fake()->createWithContent(
                    'résumé%25.pdf',
                    "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF\n",
                ),
            ])
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

        $blockId = (int) DB::table('link_page_blocks')->where('link_page_id', $page->id)->value('id');
        $media = $this->mediaForBlock($blockId);
        $this->assertSame('clean', $media->scan_state);
        $stored = Storage::disk(self::DISK)->get($media->path);

        $response = $this->get('/content-media/'.$media->public_id)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertStreamedContent($stored);
        $this->assertEqualsCanonicalizing(
            ['private', 'no-store'],
            array_map('trim', explode(',', (string) $response->headers->get('Cache-Control'))),
        );

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString('filename=r__sum___25.pdf', $disposition);
        $this->assertStringContainsString(
            "filename*=utf-8''".rawurlencode('résumé%25.pdf'),
            $disposition,
        );
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
    }

    public function test_media_url_is_revoked_when_its_block_or_asset_is_ineligible(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $blockId = $this->createImageBlock($owner, $page);
        $block = LinkPageBlock::query()->findOrFail($blockId);
        $media = $this->mediaForBlock($blockId);
        $url = '/content-media/'.$media->public_id;

        $this->get($url)->assertOk();

        $block->forceFill(['is_active' => false])->save();
        $this->get($url)->assertNotFound();

        $block->forceFill([
            'is_active' => true,
            'ends_at' => now('UTC')->subSecond(),
        ])->save();
        $this->get($url)->assertNotFound();

        $block->forceFill(['ends_at' => null])->save();
        $page->forceFill(['is_published' => false])->save();
        $this->get($url)->assertNotFound();

        $page->forceFill(['is_published' => true])->save();
        $media->forceFill(['expires_at' => now('UTC')->subSecond()])->save();
        $this->get($url)->assertNotFound();

        $media->forceFill(['expires_at' => now('UTC')->addDay()])->save();
        $this->actingAs($owner)
            ->delete("/dashboard/link-pages/{$page->id}/blocks/{$blockId}")
            ->assertRedirect("/dashboard/link-pages/{$page->id}");
        $this->get($url)->assertNotFound();
    }

    public function test_draft_media_and_another_workspaces_media_id_are_not_reusable(): void
    {
        $foreignOwner = User::factory()->create();
        $draft = $this->createPage($foreignOwner, ['is_published' => false]);
        $foreignBlockId = $this->createImageBlock($foreignOwner, $draft);
        $foreignMedia = $this->mediaForBlock($foreignBlockId);
        $this->get('/content-media/'.$foreignMedia->public_id)->assertNotFound();

        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'image',
                'is_active' => true,
                'alt_text' => 'New artwork',
                'image' => UploadedFile::fake()->image('new.png', 80, 60),
                'content_media_id' => $foreignMedia->id,
            ])
            ->assertSessionHasErrors('content_media_id');
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'file',
                'is_active' => true,
                'file' => UploadedFile::fake()->createWithContent(
                    'new.pdf',
                    "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF\n",
                ),
                'content_media_id' => $foreignMedia->id,
            ])
            ->assertSessionHasErrors('content_media_id');

        $this->assertDatabaseMissing('link_page_blocks', [
            'link_page_id' => $page->id,
            'content_media_id' => $foreignMedia->id,
        ]);
    }

    public function test_missing_clamav_leaves_no_file_block_media_or_storage_object(): void
    {
        config()->set('services.linkpilot.media.clamav_path', '/missing/linkpilot-clamscan');
        $owner = User::factory()->create();
        $page = $this->createPage($owner);

        $this->actingAs($owner)
            ->from("/dashboard/link-pages/{$page->id}")
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'file',
                'is_active' => true,
                'file' => UploadedFile::fake()->createWithContent(
                    'brief.pdf',
                    "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF\n",
                ),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('link_page_blocks', ['link_page_id' => $page->id]);
        $this->assertDatabaseCount('content_media', 0);
        $this->assertSame([], Storage::disk(self::DISK)->allFiles());
    }

    /** @param array<string, mixed> $overrides */
    private function createPage(User $user, array $overrides = []): LinkPage
    {
        $payload = array_replace([
            'name' => 'Media page',
            'slug' => 'media-'.Str::lower(Str::random(8)),
            'title' => 'Media delivery',
            'description' => 'A page with controlled media.',
            'is_published' => true,
        ], $overrides);

        $this->actingAs($user)
            ->post('/dashboard/link-pages', $payload)
            ->assertRedirect();

        return LinkPage::query()->where('slug', $payload['slug'])->sole();
    }

    private function createImageBlock(User $user, LinkPage $page): int
    {
        $this->actingAs($user)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'image',
                'is_active' => true,
                'alt_text' => 'Launch artwork',
                'image' => UploadedFile::fake()->image('launch.png', 80, 60),
            ])
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

        return (int) DB::table('link_page_blocks')
            ->where('link_page_id', $page->id)
            ->orderByDesc('id')
            ->value('id');
    }

    private function mediaForBlock(int $blockId): ContentMedia
    {
        $mediaId = (int) DB::table('link_page_blocks')->where('id', $blockId)->value('content_media_id');

        return ContentMedia::query()->findOrFail($mediaId);
    }

    private function clamavAvailable(): bool
    {
        $scanner = (string) config('services.linkpilot.media.clamav_path', 'clamscan');
        $process = new Process([$scanner, '--version']);
        $process->setTimeout(5);

        try {
            $process->run();
        } catch (ProcessStartFailedException) {
            return false;
        }

        return $process->isSuccessful();
    }
}
