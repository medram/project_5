<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Content\ContentMediaStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ContentMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    private const DISK = 'content-media-test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.'.self::DISK, ['driver' => 'local']);
        Storage::fake(self::DISK);
        config()->set('services.linkpilot.media.disk', self::DISK);
        config()->set('services.linkpilot.media.retention_days', 365);
        config()->set('services.linkpilot.media.clamav_path', '/missing/linkpilot-clamscan');
    }

    public function test_png_is_reencoded_with_metadata_removed_and_opaque_storage_path(): void
    {
        $now = now('UTC')->startOfSecond();
        $this->travelTo($now);
        [$user, $workspace] = $this->createOwnerAndWorkspace();
        $upload = UploadedFile::fake()->createWithContent('brand.png', $this->pngWithTextMetadata());
        $media = app(ContentMediaStorage::class)->store($upload, $workspace, $user, 'image');
        $disk = Storage::disk(self::DISK);
        $stored = $disk->get($media->path);
        $dimensions = getimagesizefromstring($stored);
        $this->assertNotFalse($dimensions);

        $this->assertSame($workspace->getKey(), $media->workspace_id);
        $this->assertSame($user->getKey(), $media->uploaded_by);
        $this->assertStringStartsWith("workspaces/{$workspace->id}/content-media/", $media->path);
        $this->assertStringNotContainsString('brand.png', basename($media->path));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/i', $media->public_id);
        $this->assertSame('brand.png', $media->original_name);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame('not_required', $media->scan_state);
        $this->assertSame('private', $media->visibility);
        $this->assertSame(hash('sha256', $stored), $media->sha256);
        $this->assertSame(24, $dimensions[0]);
        $this->assertSame(16, $dimensions[1]);
        $this->assertStringNotContainsString('private-marker', $stored);
        $this->assertArrayNotHasKey('disk', $media->toArray());
        $this->assertArrayNotHasKey('path', $media->toArray());
        $this->assertEquals($now->copy()->addDays(365), $media->expires_at);
    }

    public function test_jpeg_exif_marker_is_removed_during_reencoding(): void
    {
        [$user, $workspace] = $this->createOwnerAndWorkspace();
        $jpeg = $this->jpegWithExifMarker();
        $upload = UploadedFile::fake()->createWithContent('portrait.jpg', $jpeg);

        $media = app(ContentMediaStorage::class)->store($upload, $workspace, $user, 'image');
        $stored = Storage::disk(self::DISK)->get($media->path);

        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertStringNotContainsString('Exif', $stored);
        $this->assertStringNotContainsString('private-marker', $stored);
        $this->assertNotSame($jpeg, $stored);
    }

    public function test_invalid_or_mismatched_image_bytes_are_rejected_before_storage(): void
    {
        [$user, $workspace] = $this->createOwnerAndWorkspace();
        $invalid = UploadedFile::fake()->createWithContent('broken.png', 'not an image');

        try {
            app(ContentMediaStorage::class)->store($invalid, $workspace, $user, 'image');
            $this->fail('Invalid image bytes must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('content_media', 0);
            $this->assertSame([], Storage::disk(self::DISK)->allFiles());
        }
    }

    public function test_pdf_is_rejected_without_a_real_scanner_and_leaves_no_object(): void
    {
        [$user, $workspace] = $this->createOwnerAndWorkspace();
        $pdf = UploadedFile::fake()->createWithContent('brief.pdf', "%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF\n");
        config()->set('services.linkpilot.media.clamav_path', '/missing/linkpilot-clamscan');

        try {
            app(ContentMediaStorage::class)->store($pdf, $workspace, $user, 'file');
            $this->fail('A PDF must fail closed when ClamAV is unavailable.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('content_media', 0);
            $this->assertSame([], Storage::disk(self::DISK)->allFiles());
        }
    }

    public function test_workspace_mismatch_is_denied_without_writing_media(): void
    {
        [$owner, $workspace] = $this->createOwnerAndWorkspace();
        $otherUser = User::factory()->create();
        $upload = UploadedFile::fake()->createWithContent('brand.png', $this->pngWithTextMetadata());

        try {
            app(ContentMediaStorage::class)->store($upload, $workspace, $otherUser, 'image');
            $this->fail('A user outside the workspace must not store media there.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('content_media', 0);
            $this->assertSame([], Storage::disk(self::DISK)->allFiles());
        }
    }

    public function test_database_write_failure_removes_the_uploaded_object(): void
    {
        [$user, $workspace] = $this->createOwnerAndWorkspace();
        $upload = UploadedFile::fake()->createWithContent('brand.png', $this->pngWithTextMetadata());
        Event::listen('eloquent.creating: App\\Models\\ContentMedia', static function (): void {
            throw new RuntimeException('simulated database write failure');
        });

        try {
            app(ContentMediaStorage::class)->store($upload, $workspace, $user, 'image');
            $this->fail('The simulated database error must be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('simulated database write failure', $exception->getMessage());
            $this->assertSame([], Storage::disk(self::DISK)->allFiles());
            $this->assertDatabaseCount('content_media', 0);
        }
    }

    public function test_soft_deleted_and_expired_objects_are_purged_but_active_media_remains(): void
    {
        [$user, $workspace] = $this->createOwnerAndWorkspace();
        $storage = app(ContentMediaStorage::class);
        $expired = $storage->store(
            UploadedFile::fake()->createWithContent('expired.png', $this->pngWithTextMetadata()),
            $workspace,
            $user,
            'image',
        );
        $deleted = $storage->store(
            UploadedFile::fake()->createWithContent('deleted.png', $this->pngWithTextMetadata()),
            $workspace,
            $user,
            'image',
        );
        $active = $storage->store(
            UploadedFile::fake()->createWithContent('active.png', $this->pngWithTextMetadata()),
            $workspace,
            $user,
            'image',
        );
        $expired->forceFill(['expires_at' => now('UTC')->subSecond()])->save();
        $storage->delete($deleted);
        $disk = Storage::disk(self::DISK);

        $this->assertNotNull($deleted->fresh()->deleted_at);
        $this->assertTrue($disk->exists($deleted->path));
        $this->assertSame(0, Artisan::call('linkpilot:prune-content-media'), Artisan::output());

        $this->assertDatabaseMissing('content_media', ['id' => $expired->id]);
        $this->assertDatabaseMissing('content_media', ['id' => $deleted->id]);
        $this->assertDatabaseHas('content_media', ['id' => $active->id]);
        $this->assertFalse($disk->exists($expired->path));
        $this->assertFalse($disk->exists($deleted->path));
        $this->assertTrue($disk->exists($active->path));
    }

    /** @return array{User, Workspace} */
    private function createOwnerAndWorkspace(): array
    {
        $user = User::factory()->create();
        /** @var Workspace $workspace */
        $workspace = $user->personalWorkspace()->firstOrFail();

        return [$user, $workspace];
    }

    private function pngWithTextMetadata(): string
    {
        ob_start();
        $image = imagecreatetruecolor(24, 16);
        imagepng($image);
        imagedestroy($image);
        $png = (string) ob_get_clean();

        $payload = "Comment\0private-marker";
        $chunk = pack('N', strlen($payload)).'tEXt'.$payload.pack('N', crc32('tEXt'.$payload));
        $header = unpack('Nlength', substr($png, 8, 4));
        if ($header === false) {
            throw new RuntimeException('Could not inspect generated PNG.');
        }

        $ihdrEnd = 8 + 4 + 4 + $header['length'] + 4;

        return substr($png, 0, $ihdrEnd).$chunk.substr($png, $ihdrEnd);
    }

    private function jpegWithExifMarker(): string
    {
        ob_start();
        $image = imagecreatetruecolor(24, 16);
        imagejpeg($image);
        imagedestroy($image);
        $jpeg = (string) ob_get_clean();

        $payload = "Exif\0\0private-marker";
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }
}
