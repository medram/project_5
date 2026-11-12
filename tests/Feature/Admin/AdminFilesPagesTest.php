<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\CustomPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminFilesPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_files_and_pages_enforce_separate_permissions(): void
    {
        $ordinary = User::factory()->create();
        $support = User::factory()->support()->create();
        $administrator = User::factory()->admin()->create();

        $this->actingAs($ordinary)->get('/admin/files')->assertForbidden();
        $this->actingAs($support)->get('/admin/files')->assertOk();
        $this->actingAs($support)->get('/admin/custom-pages')->assertForbidden();
        $this->actingAs($administrator)->get('/admin/custom-pages')->assertOk();
    }

    public function test_file_metadata_is_safe_and_soft_delete_restore_are_audited(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $media = ContentMedia::query()->create([
            'workspace_id' => $workspace->id,
            'uploaded_by' => $administrator->id,
            'public_id' => (string) Str::uuid(),
            'disk' => 'local',
            'path' => 'workspaces/secret/private-file.pdf',
            'original_name' => 'retained.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => 2048,
            'sha256' => hash('sha256', 'retained'),
            'visibility' => 'private',
            'scan_state' => 'clean',
            'purpose' => MediaPurpose::File,
            'expires_at' => now()->addDay(),
        ]);

        $this->withoutVite();
        $response = $this->actingAs($administrator)
            ->get('/admin/files')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/files/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.originalName', 'retained.pdf')
                ->where('rows.data.0.sha256', $media->sha256)
                ->missing('rows.data.0.path')
                ->missing('rows.data.0.disk'));

        $response->assertDontSee('workspaces/secret/private-file.pdf');

        $this->actingAs($administrator)
            ->delete("/admin/files/{$media->id}", ['reason' => 'No longer needed.'])
            ->assertRedirect();
        $this->assertSoftDeleted('content_media', ['id' => $media->id]);
        $this->assertSame('No longer needed.', $media->refresh()->getAttribute('deletion_reason'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'soft_delete_file',
            'subject_id' => $media->id,
        ]);

        $this->actingAs($administrator)
            ->patch("/admin/files/{$media->id}/restore", ['reason' => 'Restored by support.'])
            ->assertRedirect();
        $this->assertNotSoftDeleted('content_media', ['id' => $media->id]);
        $this->assertNull($media->refresh()->getAttribute('deletion_reason'));
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'restore_file',
            'subject_id' => $media->id,
        ]);

        $this->actingAs(User::factory()->support()->create())
            ->delete("/admin/files/{$media->id}", ['reason' => 'Support cannot delete.'])
            ->assertForbidden();
    }

    public function test_custom_pages_sanitize_writes_render_reserved_slugs_and_publication(): void
    {
        $administrator = User::factory()->admin()->create();
        $this->withoutVite();

        $this->actingAs($administrator)
            ->post('/admin/custom-pages', [
                'slug' => 'about-us',
                'title' => 'About us',
                'body' => '<script>alert(1)</script><p onclick="evil()">Hello <a href="javascript:alert(1)">bad</a> <a href="https://example.com" rel="opener">good</a></p><iframe src="https://evil.test"></iframe>',
                'state' => 'draft',
                'placement' => 'footer',
                'seo_title' => 'About LinkPilot',
                'seo_description' => 'Safe page',
            ])
            ->assertRedirect();

        $page = CustomPage::query()->sole();
        $this->assertStringNotContainsString('<script', $page->body);
        $this->assertStringNotContainsString('javascript:', $page->body);
        $this->assertStringNotContainsString('<iframe', $page->body);
        $this->assertStringContainsString('href="https://example.com"', $page->body);
        $this->assertStringContainsString('rel="noopener noreferrer"', $page->body);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'create_custom_page']);

        $this->get('/content/about-us')->assertNotFound();

        $this->actingAs($administrator)
            ->patch("/admin/custom-pages/{$page->id}/publish")
            ->assertRedirect();
        $this->assertNotNull($page->refresh()->published_at);
        $this->get('/content/about-us')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('content/show')
                ->where('preview', false)
                ->where('page.title', 'About us'));

        $this->actingAs($administrator)
            ->get("/admin/custom-pages/{$page->id}/preview")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('content/show')
                ->where('preview', true));

        DB::table('custom_pages')->where('id', $page->id)->update([
            'body' => '<script>alert(1)</script><p>Unsafe raw body</p>',
        ]);
        $this->get('/content/about-us')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>');

        $this->actingAs($administrator)
            ->post('/admin/custom-pages', [
                'slug' => 'admin',
                'title' => 'Reserved',
                'body' => '<p>Body</p>',
                'state' => 'draft',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_custom_page_update_is_sanitized_audited_and_deletable(): void
    {
        $administrator = User::factory()->admin()->create();
        $page = CustomPage::query()->create([
            'slug' => 'terms',
            'title' => 'Terms',
            'body' => '<p>Original</p>',
            'state' => 'draft',
            'created_by' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->patch("/admin/custom-pages/{$page->id}", [
                'slug' => 'terms',
                'title' => 'Terms updated',
                'body' => '<p>Updated</p><img src="x" onerror="alert(1)">',
                'state' => 'published',
                'placement' => 'header',
            ])
            ->assertRedirect("/admin/custom-pages/{$page->id}/edit");

        $page->refresh();
        $this->assertSame('Terms updated', $page->title);
        $this->assertSame('published', $page->state);
        $this->assertNotNull($page->published_at);
        $this->assertStringNotContainsString('<img', $page->body);
        $this->assertStringNotContainsString('onerror', $page->body);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'update_custom_page']);

        $this->actingAs($administrator)
            ->delete("/admin/custom-pages/{$page->id}")
            ->assertRedirect('/admin/custom-pages');
        $this->assertDatabaseMissing('custom_pages', ['id' => $page->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'delete_custom_page']);
    }

    public function test_duplicate_slugs_and_foreign_og_media_are_rejected(): void
    {
        $administrator = User::factory()->admin()->create();
        CustomPage::query()->create([
            'slug' => 'existing',
            'title' => 'Existing',
            'body' => '<p>Body</p>',
            'state' => 'draft',
        ]);

        $this->actingAs($administrator)
            ->post('/admin/custom-pages', [
                'slug' => 'existing',
                'title' => 'Duplicate',
                'body' => '<p>Body</p>',
                'state' => 'draft',
            ])
            ->assertSessionHasErrors('slug');

        $this->actingAs($administrator)
            ->post('/admin/custom-pages', [
                'slug' => 'new-page',
                'title' => 'New',
                'body' => '<p>Body</p>',
                'state' => 'draft',
                'og_media_id' => 999999,
            ])
            ->assertSessionHasErrors('og_media_id');
    }
}
