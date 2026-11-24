<?php

namespace Tests\Feature\Admin;

use App\Models\BioPage;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\PlatformRole;
use App\Models\QrCode;
use App\Models\TrackingPixel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminContentEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_edits_another_workspaces_folder_without_impersonation(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $folder = Folder::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'name' => 'Original folder',
        ]);
        $actorWorkspace = $admin->current_workspace_id;
        $actorMemberships = $admin->workspaces()->count();

        $this->actingAs($admin)->patch("/admin/folders/{$folder->id}", [
            'name' => 'Edited folder',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/folders');

        $folder->refresh();
        $this->assertSame('Edited folder', $folder->name);
        $this->assertSame($owner->id, $folder->user_id);
        $this->assertSame($owner->current_workspace_id, $folder->workspace_id);
        $this->assertSame($actorWorkspace, $admin->fresh()->current_workspace_id);
        $this->assertSame($actorMemberships, $admin->workspaces()->count());
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'permission' => 'admin.content.folders.manage',
            'subject_id' => $folder->id,
        ]);
        $this->assertFalse($admin->workspaces()->whereKey($owner->current_workspace_id)->exists());
    }

    public function test_support_and_non_admins_cannot_edit_or_preview_other_owners_records(): void
    {
        $owner = User::factory()->create();
        $folder = Folder::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'name' => 'Protected folder',
        ]);
        $link = Link::factory()->for($owner)->create(['workspace_id' => $owner->current_workspace_id]);
        foreach ([User::factory()->support()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get("/admin/folders/{$folder->id}")->assertForbidden();
            $this->actingAs($user)->patch("/admin/folders/{$folder->id}", ['name' => 'Forbidden'])->assertForbidden();
            $this->actingAs($user)->get("/admin/qr-codes/{$link->qrCode->id}/preview.svg")->assertForbidden();
        }
        $this->assertSame('Protected folder', $folder->fresh()->name);
        $this->assertSame(0, DB::table('admin_audit_logs')->count());
    }

    public function test_admin_edits_a_bio_profile_and_its_nested_link(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $bio = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'handle' => 'admin-edit-bio', 'display_name' => 'Original', 'is_published' => true,
        ]);
        $link = $bio->bioLinks()->create(['title' => 'Website', 'destination_url' => 'https://example.com/old', 'position' => 0, 'is_active' => true]);
        $this->actingAs($admin)->patch("/admin/biolinks/{$bio->id}", [
            'handle' => $bio->handle, 'display_name' => 'Edited profile', 'is_published' => true,
        ])->assertSessionHasNoErrors()->assertRedirect("/admin/biolinks/{$bio->id}");
        $this->actingAs($admin)->patch("/admin/biolinks/{$bio->id}/links/{$link->id}", [
            'title' => 'Edited website', 'destination_url' => 'https://example.com/new', 'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect("/admin/biolinks/{$bio->id}");
        $this->assertSame('Edited profile', $bio->fresh()->display_name);
        $this->assertSame('Edited website', $link->fresh()->title);
        $this->assertSame($owner->current_workspace_id, $bio->fresh()->workspace_id);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'permission' => 'admin.content.biolinks.manage', 'subject_id' => $bio->id]);
    }

    public function test_qr_only_editor_can_edit_campaigns_without_seeing_link_destinations(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $role = PlatformRole::query()->create(['name' => 'QR editor', 'code' => 'qr_editor']);
        $role->permissionGrants()->createMany([
            ['permission' => 'admin.access'], ['permission' => 'admin.content.qr_codes.view'], ['permission' => 'admin.content.qr_codes.manage'],
        ]);
        $editor->platformRoles()->attach($role);
        $otherLink = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id, 'title' => 'Selectable link',
            'destination_url' => 'https://secret.example/private-link', 'access_code' => 'secret-passphrase',
        ]);
        $qr = QrCode::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'source' => 'campaign',
            'title' => 'Original QR', 'destination_url' => 'https://example.com/qr', 'created_by' => $owner->id,
        ]);
        $this->withoutVite()->actingAs($editor)->get("/admin/qr-codes/{$qr->id}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/content/index')
            ->where('editing.resource', 'qr-codes')
            ->where('editing.record.id', $qr->id)
            ->where('editing.availableLinks.0.id', $otherLink->id)
            ->missing('editing.availableLinks.0.destination_url')
            ->missing('editing.availableLinks.0.access_code'))
            ->assertDontSee('https://secret.example/private-link')->assertDontSee('secret-passphrase');
        $this->actingAs($editor)->patch("/admin/qr-codes/{$qr->id}", ['title' => 'Edited QR'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/qr-codes');
        $this->assertSame('Edited QR', $qr->fresh()->title);
        $this->actingAs($editor)->patch("/admin/qr-codes/{$otherLink->qrCode->id}", [
            'link_id' => null, 'destination_url' => 'https://example.com/forged',
        ])->assertSessionHasErrors('link_id');
        $this->assertSame($otherLink->id, $otherLink->qrCode->fresh()->link_id);
        $this->actingAs($editor)->get('/admin/links')->assertForbidden();
    }

    public function test_admin_edits_a_link_page_and_nested_item(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $page = LinkPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'name' => 'Original page', 'slug' => 'admin-edit-page', 'title' => 'Original page', 'is_published' => true,
        ]);
        $variant = $page->controlVariant()->sole();
        $item = $page->items()->create([
            'link_page_variant_id' => $variant->id, 'title' => 'Original item', 'destination_url' => 'https://example.com/old', 'position' => 0, 'is_active' => true,
        ]);
        $this->actingAs($admin)->patch("/admin/link-pages/{$page->id}", [
            'name' => 'Edited page', 'slug' => $page->slug, 'title' => 'Edited page', 'is_published' => true,
        ])->assertSessionHasNoErrors()->assertRedirect("/admin/link-pages/{$page->id}");
        $this->actingAs($admin)->patch("/admin/link-pages/{$page->id}/items/{$item->id}", [
            'title' => 'Edited item', 'destination_url' => 'https://example.com/new', 'is_active' => true, 'link_page_variant_id' => $variant->id,
        ])->assertSessionHasNoErrors()->assertRedirect("/admin/link-pages/{$page->id}");
        $this->assertSame('Edited page', $page->fresh()->name);
        $this->assertSame('Edited item', $item->fresh()->title);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'permission' => 'admin.content.link_pages.manage', 'subject_id' => $page->id]);
    }

    public function test_admin_edits_a_short_link_and_preserves_its_owner(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create(['workspace_id' => $owner->current_workspace_id, 'slug' => 'admin-edit-link']);
        $this->actingAs($admin)->patch("/admin/links/{$link->id}", [
            'delivery_mode' => 'direct', 'destination_url' => 'https://example.com/new',
            'title' => 'Edited link', 'slug' => $link->slug, 'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/links');
        $this->assertSame('Edited link', $link->fresh()->title);
        $this->assertSame($owner->id, $link->fresh()->user_id);
        $this->assertSame($owner->current_workspace_id, $link->fresh()->workspace_id);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'permission' => 'admin.content.links.manage', 'subject_id' => $link->id]);
    }

    public function test_admin_edits_an_overlay_and_pixel(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $overlay = LinkOverlay::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id, 'name' => 'Original overlay',
            'heading' => 'Heading', 'cta_label' => 'Open', 'is_active' => true,
        ]);
        $pixel = TrackingPixel::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id, 'name' => 'Original pixel',
            'provider' => 'meta_pixel', 'provider_id' => '123456', 'is_active' => true,
        ]);
        $this->actingAs($admin)->patch("/admin/link-overlays/{$overlay->id}", [
            'name' => 'Edited overlay', 'heading' => 'Heading', 'cta_label' => 'Open', 'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/link-overlays');
        $this->actingAs($admin)->patch("/admin/pixels/{$pixel->id}", [
            'name' => 'Edited pixel', 'provider' => 'meta_pixel', 'provider_id' => '123456', 'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/pixels');
        $this->assertSame('Edited overlay', $overlay->fresh()->name);
        $this->assertSame('Edited pixel', $pixel->fresh()->name);
    }

    public function test_metadata_rows_expose_edit_actions_only_to_managers(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id, 'destination_url' => 'https://secret.example/private',
        ]);
        $this->withoutVite()->actingAs(User::factory()->admin()->create())->get('/admin/links')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.editUrl', "/admin/links/{$link->id}")
            ->where('editing', null))
            ->assertDontSee('https://secret.example/private');
        $this->actingAs(User::factory()->support()->create())->get('/admin/links')
            ->assertOk()->assertInertia(fn (Assert $page) => $page->missing('rows.data.0.editUrl'));
    }

    public function test_admin_content_updates_return_to_the_filtered_inventory(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id,
            'slug' => 'filtered-update',
        ]);

        $this->actingAs($admin)->patch("/admin/links/{$link->id}?q=filtered-update", [
            'delivery_mode' => 'direct',
            'destination_url' => 'https://example.com/filtered',
            'title' => 'Filtered update',
            'slug' => $link->slug,
            'is_active' => true,
        ])->assertSessionHasNoErrors()
            ->assertRedirect('/admin/links?q=filtered-update');
    }

    public function test_manager_without_inventory_view_cannot_open_the_editor(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id,
            'slug' => 'manage-only-link',
        ]);
        $editor = User::factory()->create();
        $role = PlatformRole::query()->create(['name' => 'Link writer', 'code' => 'link_writer']);
        $role->permissionGrants()->createMany([
            ['permission' => 'admin.access'],
            ['permission' => 'admin.content.links.manage'],
        ]);
        $editor->platformRoles()->attach($role);

        $this->actingAs($editor)->get("/admin/links/{$link->id}")->assertForbidden();
        $this->actingAs($editor)->patch("/admin/links/{$link->id}", [
            'delivery_mode' => 'direct',
            'destination_url' => 'https://example.com/manage-only',
            'slug' => $link->slug,
            'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/links');
        $this->assertSame('https://example.com/manage-only', $link->fresh()->destination_url);
    }

    public function test_edit_routes_render_the_editor_over_the_inventory(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id,
            'title' => 'Inventory link',
            'slug' => 'inventory-link',
        ]);

        $this->withoutVite()->actingAs($admin)->get("/admin/links/{$link->id}?q=Inventory")
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/content/index')
            ->where('resource.key', 'links')
            ->where('rows.data.0.name', 'Inventory link')
            ->where('filters.q', 'Inventory')
            ->where('editing.resource', 'links')
            ->where('editing.record.id', $link->id)
            ->where('editing.backUrl', '/admin/links?q=Inventory'));
    }

    public function test_foreign_link_associations_are_rejected_without_audit_or_changes(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create(['workspace_id' => $owner->current_workspace_id, 'slug' => 'admin-foreign']);
        $foreign = Folder::query()->create(['workspace_id' => $other->current_workspace_id, 'user_id' => $other->id, 'name' => 'Foreign']);
        $destination = $link->destination_url;

        $this->actingAs($admin)->patch("/admin/links/{$link->id}", [
            'delivery_mode' => 'direct', 'destination_url' => 'https://example.com/new',
            'title' => 'Changed', 'slug' => $link->slug, 'is_active' => true, 'folder_id' => $foreign->id,
        ])->assertSessionHasErrors('folder_id');
        $this->assertSame($destination, $link->fresh()->destination_url);
        $this->assertNull($link->fresh()->folder_id);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_link_approval_stays_pending_and_audit_contains_no_private_values(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id, 'slug' => 'admin-approval',
            'destination_url' => 'https://example.com/original', 'approval_required' => true, 'approval_status' => 'approved',
        ]);
        $this->actingAs($admin)->patch("/admin/links/{$link->id}", [
            'delivery_mode' => 'direct', 'destination_url' => 'https://secret.example/proposed',
            'title' => 'Proposed', 'slug' => $link->slug, 'is_active' => true,
            'approval_required' => true, 'access_code' => 'private-access-value',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/links');
        $this->assertSame('https://example.com/original', $link->fresh()->destination_url);
        $this->assertDatabaseHas('link_revisions', [
            'link_id' => $link->id, 'actor_user_id' => $admin->id, 'status' => 'pending',
        ]);
        $changes = (string) DB::table('admin_audit_logs')->where('subject_id', $link->id)->value('changes');
        $this->assertStringNotContainsString('https://secret.example/proposed', $changes);
        $this->assertStringNotContainsString('private-access-value', $changes);
    }

    public function test_failed_admin_audit_rolls_back_media_and_removes_uploaded_bytes(): void
    {
        $disk = 'admin-bio-media-test';
        config()->set('services.linkpilot.media.disk', $disk);
        config()->set('filesystems.disks.'.$disk, ['driver' => 'local', 'root' => storage_path('framework/testing/disks/'.$disk)]);
        Storage::fake($disk);
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $page = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'handle' => 'admin-media-test', 'display_name' => 'Media', 'is_published' => true,
        ]);
        DB::statement("CREATE TRIGGER reject_admin_audit BEFORE INSERT ON admin_audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

        $this->actingAs($admin)->post("/admin/biolinks/{$page->id}/blocks", [
            'type' => 'image', 'is_active' => true, 'alt_text' => 'Example',
            'image' => UploadedFile::fake()->image('example.png', 3, 3),
        ])->assertStatus(500);
        $this->assertSame(0, $page->contentBlocks()->count());
        $this->assertDatabaseCount('content_media', 0);
        $this->assertSame([], Storage::disk($disk)->allFiles());
    }

    public function test_pixel_rules_are_scoped_to_the_selected_workspace_and_audited(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $pixel = TrackingPixel::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id, 'name' => 'Pixel',
            'provider' => 'meta_pixel', 'provider_id' => '123456', 'is_active' => true,
        ]);
        $local = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'handle' => 'pixel-local', 'display_name' => 'Local', 'is_published' => true,
        ]);
        $foreign = BioPage::query()->create([
            'workspace_id' => $other->current_workspace_id, 'user_id' => $other->id,
            'handle' => 'pixel-foreign', 'display_name' => 'Foreign', 'is_published' => true,
        ]);
        $this->actingAs($admin)->post("/admin/pixels/{$pixel->id}/rules", [
            'target_type' => 'bio_page', 'target_id' => $foreign->id, 'trigger' => 'page_view',
        ])->assertNotFound();
        $this->assertDatabaseCount('admin_audit_logs', 0);
        $this->actingAs($admin)->post("/admin/pixels/{$pixel->id}/rules", [
            'target_type' => 'bio_page', 'target_id' => $local->id, 'trigger' => 'page_view',
        ])->assertSessionHasNoErrors()->assertRedirect("/admin/pixels/{$pixel->id}");
        $rule = $pixel->rules()->sole();
        $this->actingAs($admin)->delete("/admin/pixels/{$pixel->id}/rules/{$rule->id}")
            ->assertRedirect("/admin/pixels/{$pixel->id}");
        $this->assertDatabaseMissing('tracking_pixel_rules', ['id' => $rule->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'permission' => 'admin.content.pixels.manage', 'subject_id' => $pixel->id]);
    }

    public function test_link_page_variants_and_blocks_stay_with_the_selected_page(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $page = LinkPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'name' => 'Campaign', 'slug' => 'admin-variant', 'title' => 'Campaign', 'is_published' => true,
        ]);
        $this->actingAs($admin)->post("/admin/link-pages/{$page->id}/variants", ['name' => 'Variation', 'weight' => 30])
            ->assertSessionHasNoErrors()->assertRedirect();
        $variant = $page->variants()->where('name', 'Variation')->sole();
        $this->actingAs($admin)->post("/admin/link-pages/{$page->id}/blocks", [
            'type' => 'text', 'text' => 'New copy', 'is_active' => true, 'link_page_variant_id' => $variant->id,
        ])->assertSessionHasNoErrors()->assertRedirect("/admin/link-pages/{$page->id}?variant={$variant->id}");
        $block = $page->blocks()->sole();
        $this->assertSame('New copy', $block->content['text']);
        $this->assertSame($variant->id, $block->link_page_variant_id);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'permission' => 'admin.content.link_pages.manage', 'subject_id' => $page->id]);
    }

    public function test_domain_rename_stops_the_old_hostname_resolving(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $domain = $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id, 'hostname' => 'old-domain.example.test',
            'verification_token' => 'old-token', 'status' => 'active', 'verified_at' => now(),
        ]);
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id, 'custom_domain_id' => $domain->id, 'slug' => 'domain-admin-test',
        ]);
        $this->actingAs($admin)->patch("/admin/custom-domains/{$domain->id}", [
            'hostname' => 'new-domain.example.test', 'confirmation' => 'old-domain.example.test',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/custom-domains');

        $url = app('url');
        $url->forceRootUrl('http://old-domain.example.test');
        try {
            $this->get("/{$link->slug}")->assertNotFound();
        } finally {
            $url->forceRootUrl((string) config('app.url'));
        }
    }

    public function test_admin_audit_records_only_fields_that_actually_changed(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $folder = Folder::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id, 'name' => 'Before',
        ]);
        $this->actingAs($admin)->patch("/admin/folders/{$folder->id}", [
            'name' => 'After', 'title' => 'ignored client input',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/folders');

        $changes = json_decode((string) DB::table('admin_audit_logs')->sole()->changes, true);
        $this->assertSame(['name'], array_values($changes['fields']));
        $this->assertStringNotContainsString('ignored client input', json_encode($changes));
    }
}
