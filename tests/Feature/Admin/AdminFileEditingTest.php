<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminFileEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_rename_updates_listing_search_and_download_without_changing_provenance_or_bytes(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $media = $this->media($admin);
        Storage::disk('local')->put($media->path, 'original bytes');

        $this->actingAs($admin)->post('/dashboard/link-pages', [
            'name' => 'Media page', 'slug' => 'media-page', 'title' => 'Media page',
            'description' => 'Files', 'is_published' => true,
        ])->assertRedirect();
        $page = LinkPage::query()->where('slug', 'media-page')->sole();
        LinkPageBlock::query()->create([
            'link_page_id' => $page->id, 'content_media_id' => $media->id,
            'type' => 'file', 'content' => [], 'position' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)->patch("/admin/files/{$media->id}", [
            'display_name' => 'renamed.pdf', 'expires_at' => now('UTC')->addDays(2)->toISOString(),
            'reason' => 'Correct the file name',
        ])->assertRedirect();

        $media->refresh();
        $this->assertSame('retained.pdf', $media->original_name);
        $this->assertSame(hash('sha256', 'original bytes'), $media->sha256);
        $this->assertSame('original bytes', Storage::disk('local')->get($media->path));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'update_file', 'subject_id' => $media->id]);
        $this->withoutVite()->get('/admin/files?q=renamed')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.originalName', 'renamed.pdf'));
        $response = $this->get('/content-media/'.$media->public_id)->assertOk()->assertStreamedContent('original bytes');
        $this->assertStringContainsString('renamed.pdf', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_rejects_unsafe_names_extensions_and_missing_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $media = $this->media($admin);
        foreach (['../bad.pdf', 'dir\\bad.pdf', "bad\nname.pdf", 'changed.png'] as $name) {
            $this->actingAs($admin)->patch("/admin/files/{$media->id}", [
                'display_name' => $name, 'expires_at' => null, 'reason' => 'Correct name',
            ])->assertSessionHasErrors('display_name');
        }
        $this->actingAs($admin)->patch("/admin/files/{$media->id}", [
            'display_name' => 'valid.pdf', 'expires_at' => null,
        ])->assertSessionHasErrors('reason');
        $this->assertSame('retained.pdf', $media->refresh()->original_name);
    }

    public function test_expiry_must_be_future_and_indefinitely_retained_media_remains_without_expiry(): void
    {
        $admin = User::factory()->admin()->create();
        $file = $this->media($admin);
        $this->actingAs($admin)->patch("/admin/files/{$file->id}", [
            'display_name' => 'retained.pdf', 'expires_at' => now()->subDay()->toISOString(), 'reason' => 'Adjust retention',
        ])->assertSessionHasErrors('expires_at');
        $asset = $this->media($admin, MediaPurpose::PlatformAsset);
        $this->actingAs($admin)->patch("/admin/files/{$asset->id}", [
            'display_name' => 'asset.pdf', 'expires_at' => null, 'reason' => 'Rename asset',
        ])->assertRedirect();
        $this->assertNull($asset->refresh()->expires_at);
        $this->actingAs($admin)->patch("/admin/files/{$asset->id}", [
            'display_name' => 'asset.pdf', 'expires_at' => now()->addDay()->toISOString(), 'reason' => 'Adjust retention',
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_trashed_files_and_users_without_manage_permission_cannot_be_edited(): void
    {
        $admin = User::factory()->admin()->create();
        $media = $this->media($admin);
        $payload = ['display_name' => 'new.pdf', 'expires_at' => null, 'reason' => 'Rename'];
        $this->actingAs(User::factory()->support()->create())->patch("/admin/files/{$media->id}", $payload)->assertForbidden();
        $this->actingAs(User::factory()->create())->patch("/admin/files/{$media->id}", $payload)->assertForbidden();
        $media->delete();
        $this->actingAs($admin)->patch("/admin/files/{$media->id}", $payload)->assertNotFound();
    }

    private function media(User $owner, MediaPurpose $purpose = MediaPurpose::File): ContentMedia
    {
        return ContentMedia::query()->create([
            'workspace_id' => $owner->personalWorkspace()->firstOrFail()->id,
            'uploaded_by' => $owner->id, 'public_id' => (string) Str::uuid(),
            'disk' => 'local', 'path' => 'private/'.Str::uuid().'.pdf',
            'original_name' => 'retained.pdf', 'mime_type' => 'application/pdf',
            'byte_size' => strlen('original bytes'), 'sha256' => hash('sha256', 'original bytes'),
            'visibility' => 'private', 'scan_state' => 'clean', 'purpose' => $purpose,
            'expires_at' => $purpose->retainsIndefinitely() ? null : now()->addDay(),
        ]);
    }
}
