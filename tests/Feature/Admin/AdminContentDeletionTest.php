<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformPermission;
use App\Models\BioPage;
use App\Models\ContentMedia;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\PlatformRole;
use App\Models\TrackingPixel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Assert as PHPUnit;
use Tests\TestCase;

class AdminContentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_deletes_each_root_resource_cross_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $workspace = $owner->currentWorkspace;
        $workspaceMemberships = $admin->workspaces()->count();
        $currentWorkspace = $admin->current_workspace_id;

        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'title' => 'Deletable link',
        ]);
        $bioPage = BioPage::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'handle' => 'deletable-bio',
            'display_name' => 'Deletable Bio',
            'is_published' => true,
        ]);
        $qrCode = $workspace->qrCodes()->create([
            'source' => 'campaign',
            'title' => 'Deletable campaign',
            'destination_url' => 'https://example.com/campaign',
        ]);
        $folder = Folder::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Deletable folder',
        ]);
        $domain = CustomDomain::query()->forceCreate([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'hostname' => 'delete-me.test',
            'verification_token' => 'delete-token',
            'status' => 'pending_dns',
        ]);
        $overlay = LinkOverlay::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Deletable overlay',
            'heading' => 'Heading',
            'cta_label' => 'Open',
            'is_active' => true,
        ]);
        $linkPage = LinkPage::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Deletable page',
            'slug' => 'deletable-page',
            'title' => 'Deletable page',
            'is_published' => true,
        ]);
        $pixel = TrackingPixel::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Deletable pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '123456',
            'is_active' => true,
        ]);

        $cases = [
            [
                'url' => "/admin/links/{$link->id}",
                'list' => '/admin/links',
                'permission' => 'admin.content.links.delete',
                'action' => 'admin.content.links.destroy',
                'table' => 'links',
                'id' => $link->id,
                'data' => [],
            ],
            [
                'url' => "/admin/biolinks/{$bioPage->id}",
                'list' => '/admin/biolinks',
                'permission' => 'admin.content.biolinks.delete',
                'action' => 'admin.content.biolinks.destroy',
                'table' => 'bio_pages',
                'id' => $bioPage->id,
                'data' => [],
            ],
            [
                'url' => "/admin/qr-codes/{$qrCode->id}",
                'list' => '/admin/qr-codes',
                'permission' => 'admin.content.qr_codes.delete',
                'action' => 'admin.content.qr-codes.destroy',
                'table' => 'qr_codes',
                'id' => $qrCode->id,
                'data' => [],
            ],
            [
                'url' => "/admin/folders/{$folder->id}",
                'list' => '/admin/folders',
                'permission' => 'admin.content.folders.delete',
                'action' => 'admin.content.folders.destroy',
                'table' => 'folders',
                'id' => $folder->id,
                'data' => [],
            ],
            [
                'url' => "/admin/custom-domains/{$domain->id}",
                'list' => '/admin/custom-domains',
                'permission' => 'admin.content.domains.delete',
                'action' => 'admin.content.domains.destroy',
                'table' => 'custom_domains',
                'id' => $domain->id,
                'data' => ['confirmation' => $domain->hostname],
            ],
            [
                'url' => "/admin/link-overlays/{$overlay->id}",
                'list' => '/admin/link-overlays',
                'permission' => 'admin.content.overlays.delete',
                'action' => 'admin.content.overlays.destroy',
                'table' => 'link_overlays',
                'id' => $overlay->id,
                'data' => [],
            ],
            [
                'url' => "/admin/link-pages/{$linkPage->id}",
                'list' => '/admin/link-pages',
                'permission' => 'admin.content.link_pages.delete',
                'action' => 'admin.content.link-pages.destroy',
                'table' => 'link_pages',
                'id' => $linkPage->id,
                'data' => [],
            ],
            [
                'url' => "/admin/pixels/{$pixel->id}",
                'list' => '/admin/pixels',
                'permission' => 'admin.content.pixels.delete',
                'action' => 'admin.content.pixels.destroy',
                'table' => 'tracking_pixels',
                'id' => $pixel->id,
                'data' => [],
            ],
        ];

        foreach ($cases as $case) {
            $this->actingAs($admin)
                ->delete($case['url'], $case['data'])
                ->assertRedirect($case['list']);

            $this->assertDatabaseMissing($case['table'], ['id' => $case['id']]);
            $this->assertDatabaseHas('admin_audit_logs', [
                'permission' => $case['permission'],
                'action' => $case['action'],
                'subject_id' => $case['id'],
                'actor_user_id' => $admin->id,
            ]);
        }

        $admin->refresh();
        $this->assertSame($currentWorkspace, $admin->current_workspace_id);
        $this->assertSame($workspaceMemberships, $admin->workspaces()->count());
    }

    public function test_cascade_and_cleanup_rules_are_preserved(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $workspace = $owner->currentWorkspace;

        $link = Link::factory()->for($owner)->create(['workspace_id' => $workspace->id]);
        $linkedQr = $link->qrCode()->sole();
        $campaign = $workspace->qrCodes()->create([
            'source' => 'campaign',
            'link_id' => $link->id,
            'title' => 'Campaign pointing at the link',
        ]);
        $this->get("/q/{$campaign->code}")->assertRedirect();
        $this->assertDatabaseHas('qr_scans', ['qr_code_id' => $campaign->id]);
        $this->assertSame(1, $campaign->fresh()->scan_count);

        $this->actingAs($admin)->delete("/admin/links/{$link->id}")->assertRedirect('/admin/links');

        $this->assertDatabaseMissing('qr_codes', ['id' => $linkedQr->id]);
        $campaign->refresh();
        $this->assertNull($campaign->link_id);
        $this->assertSame(1, $campaign->scan_count);
        $this->assertDatabaseHas('qr_scans', ['qr_code_id' => $campaign->id]);
        $this->get("/q/{$campaign->code}")->assertNotFound();

        $folder = Folder::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Folder',
        ]);
        $foldered = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'folder_id' => $folder->id,
        ]);
        $this->actingAs($admin)->delete("/admin/folders/{$folder->id}")->assertRedirect('/admin/folders');
        $this->assertDatabaseHas('links', ['id' => $foldered->id, 'folder_id' => null]);

        $domain = CustomDomain::query()->forceCreate([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'hostname' => 'cascade.test',
            'verification_token' => 'cascade-token',
            'status' => 'active',
        ]);
        $domained = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'custom_domain_id' => $domain->id,
        ]);
        $this->actingAs($admin)
            ->delete("/admin/custom-domains/{$domain->id}", ['confirmation' => $domain->hostname])
            ->assertRedirect('/admin/custom-domains');
        $this->assertDatabaseHas('links', ['id' => $domained->id, 'custom_domain_id' => null]);

        $overlay = LinkOverlay::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Overlay',
            'heading' => 'Heading',
            'cta_label' => 'Open',
            'is_active' => true,
        ]);
        $overlaid = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'link_overlay_id' => $overlay->id,
        ]);
        $this->actingAs($admin)->delete("/admin/link-overlays/{$overlay->id}")->assertRedirect('/admin/link-overlays');
        $this->assertDatabaseHas('links', ['id' => $overlaid->id, 'link_overlay_id' => null]);

        $pixel = TrackingPixel::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '654321',
            'is_active' => true,
        ]);
        $rule = $pixel->rules()->create([
            'workspace_id' => $workspace->id,
            'target_type' => 'bio_page',
            'target_id' => 1,
            'trigger' => 'page_view',
        ]);
        $this->actingAs($admin)->delete("/admin/pixels/{$pixel->id}")->assertRedirect('/admin/pixels');
        $this->assertDatabaseMissing('tracking_pixel_rules', ['id' => $rule->id]);
    }

    public function test_bio_page_delete_cleans_block_media(): void
    {
        $disk = 'admin-delete-test';
        config()->set('services.linkpilot.media.disk', $disk);
        config()->set('filesystems.disks.'.$disk, [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/'.$disk),
        ]);
        Storage::fake($disk);

        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $bioPage = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'handle' => 'media-bio',
            'display_name' => 'Media Bio',
            'is_published' => true,
        ]);

        $this->actingAs($admin)->post("/admin/biolinks/{$bioPage->id}/blocks", [
            'type' => 'image',
            'is_active' => true,
            'alt_text' => 'Example',
            'image' => UploadedFile::fake()->image('example.png', 3, 3),
        ])->assertRedirect();
        $media = ContentMedia::query()->sole();

        $this->actingAs($admin)->delete("/admin/biolinks/{$bioPage->id}")->assertRedirect('/admin/biolinks');

        $this->assertDatabaseMissing('bio_pages', ['id' => $bioPage->id]);
        $this->assertDatabaseMissing('bio_content_blocks', ['bio_page_id' => $bioPage->id]);
        $this->assertSoftDeleted('content_media', ['id' => $media->id]);
        Storage::disk($disk)->assertExists($media->path);
    }

    public function test_link_page_delete_cleans_block_media_and_leads(): void
    {
        $disk = 'admin-delete-test';
        config()->set('services.linkpilot.media.disk', $disk);
        config()->set('filesystems.disks.'.$disk, [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/'.$disk),
        ]);
        Storage::fake($disk);

        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $workspace = $owner->currentWorkspace;
        $page = LinkPage::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Media page',
            'slug' => 'media-page',
            'title' => 'Media page',
            'is_published' => true,
        ]);

        $this->actingAs($admin)->post("/admin/link-pages/{$page->id}/blocks", [
            'type' => 'image',
            'is_active' => true,
            'alt_text' => 'Example',
            'image' => UploadedFile::fake()->image('example.png', 3, 3),
        ])->assertSessionHasNoErrors();
        $block = LinkPageBlock::query()->sole();
        $media = ContentMedia::query()->sole();

        DB::table('link_page_leads')->insert([
            'workspace_id' => $workspace->id,
            'link_page_id' => $page->id,
            'link_page_block_id' => $block->id,
            'responses' => '{}',
            'consented_at' => now(),
            'retention_expires_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete("/admin/link-pages/{$page->id}")->assertRedirect('/admin/link-pages');

        $this->assertDatabaseMissing('link_pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('link_page_blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('link_page_leads', ['link_page_id' => $page->id]);
        $this->assertSoftDeleted('content_media', ['id' => $media->id]);
    }

    public function test_delete_permission_is_independent_from_edit_and_not_granted_to_support(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;

        $deleteRole = PlatformRole::query()->create(['name' => 'Link deleter', 'code' => 'link_deleter']);
        $deleteRole->permissionGrants()->createMany([
            ['permission' => PlatformPermission::AdminAccess->value],
            ['permission' => PlatformPermission::AdminLinksView->value],
            ['permission' => PlatformPermission::AdminLinksDelete->value],
        ]);
        $deleter = User::factory()->create();
        $deleter->platformRoles()->attach($deleteRole);

        $first = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'delete-only-first',
        ]);
        $this->actingAs($deleter)->delete("/admin/links/{$first->id}")->assertRedirect('/admin/links');
        $this->assertDatabaseMissing('links', ['id' => $first->id]);

        Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'delete-only-second',
        ]);
        $this->assertLinkRowCapabilities($deleter, 'delete-only-second', edit: false, delete: true);

        $manageRole = PlatformRole::query()->create(['name' => 'Link manager', 'code' => 'link_manager']);
        $manageRole->permissionGrants()->createMany([
            ['permission' => PlatformPermission::AdminAccess->value],
            ['permission' => PlatformPermission::AdminLinksView->value],
            ['permission' => PlatformPermission::AdminLinksManage->value],
        ]);
        $manager = User::factory()->create();
        $manager->platformRoles()->attach($manageRole);

        $managed = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'manage-only',
        ]);
        $this->actingAs($manager)->delete("/admin/links/{$managed->id}")->assertForbidden();
        $this->assertDatabaseHas('links', ['id' => $managed->id]);
        $this->assertLinkRowCapabilities($manager, 'manage-only', edit: true, delete: false);

        $support = User::factory()->support()->create();
        $supportLink = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'support-link',
        ]);
        $this->actingAs($support)->delete("/admin/links/{$supportLink->id}")->assertForbidden();
        $this->assertDatabaseHas('links', ['id' => $supportLink->id]);
        $this->assertLinkRowCapabilities($support, 'support-link', edit: false, delete: false);

        $deleteOnlyRole = PlatformRole::query()->create(['name' => 'Bare deleter', 'code' => 'bare_deleter']);
        $deleteOnlyRole->permissionGrants()->createMany([
            ['permission' => PlatformPermission::AdminAccess->value],
            ['permission' => PlatformPermission::AdminLinksDelete->value],
        ]);
        $bareDeleter = User::factory()->create();
        $bareDeleter->platformRoles()->attach($deleteOnlyRole);

        $bare = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'bare-delete',
        ]);
        $this->actingAs($bareDeleter)->delete("/admin/links/{$bare->id}")->assertRedirect('/admin/links');
        $this->assertDatabaseMissing('links', ['id' => $bare->id]);
        $this->actingAs($bareDeleter)->get('/admin/links')->assertForbidden();
    }

    public function test_admin_deletes_linked_qr_rows_without_deleting_the_short_link(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $workspace = $owner->currentWorkspace;
        $link = Link::factory()->for($owner)->create(['workspace_id' => $workspace->id]);
        $linked = $link->qrCode()->sole();
        $linked->scans()->create(['scanned_at' => now()]);
        $campaign = $workspace->qrCodes()->create([
            'source' => 'campaign',
            'title' => 'Campaign',
            'destination_url' => 'https://example.com/campaign',
        ]);

        $this->withoutVite();
        $this->actingAs($admin)->get('/admin/qr-codes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/index')
                ->where('rows.data', function ($rows) use ($linked, $campaign): bool {
                    $linkedRow = $rows->firstWhere('identifier', $linked->code);
                    $campaignRow = $rows->firstWhere('identifier', $campaign->code);
                    PHPUnit::assertIsArray($linkedRow);
                    PHPUnit::assertArrayHasKey('deleteUrl', $linkedRow);
                    PHPUnit::assertIsArray($campaignRow);
                    PHPUnit::assertArrayHasKey('deleteUrl', $campaignRow);

                    return true;
                }));

        $this->actingAs($admin)
            ->delete("/admin/qr-codes/{$linked->id}")
            ->assertRedirect('/admin/qr-codes');
        $this->assertDatabaseMissing('qr_codes', ['id' => $linked->id]);
        $this->assertDatabaseMissing('qr_scans', ['qr_code_id' => $linked->id]);
        $this->assertDatabaseHas('links', ['id' => $link->id]);
        $this->assertDatabaseHas('qr_codes', ['id' => $campaign->id]);
    }

    public function test_domain_delete_requires_the_exact_hostname_confirmation(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $domain = CustomDomain::query()->forceCreate([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'hostname' => 'confirm-me.test',
            'verification_token' => 'confirm-token',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete("/admin/custom-domains/{$domain->id}")
            ->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('custom_domains', ['id' => $domain->id]);

        $this->actingAs($admin)
            ->delete("/admin/custom-domains/{$domain->id}", ['confirmation' => 'wrong.test'])
            ->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('custom_domains', ['id' => $domain->id]);

        $this->actingAs($admin)
            ->delete("/admin/custom-domains/{$domain->id}", ['confirmation' => $domain->hostname])
            ->assertRedirect('/admin/custom-domains');
        $this->assertDatabaseMissing('custom_domains', ['id' => $domain->id]);
    }

    public function test_delete_redirects_to_the_filtered_list_and_drops_the_page_parameter(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $workspace = $owner->currentWorkspace;

        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $workspace->id,
            'title' => 'Target link',
        ]);
        $this->actingAs($admin)->get('/admin/links?q=target&page=2')->assertOk();
        $this->actingAs($admin)
            ->delete("/admin/links/{$link->id}?q=target&page=2")
            ->assertRedirect('/admin/links?q=target');

        $bioPage = BioPage::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'handle' => 'filtered-bio',
            'display_name' => 'Filtered Bio',
            'is_published' => true,
        ]);
        $this->actingAs($admin)
            ->delete("/admin/biolinks/{$bioPage->id}?q=filtered")
            ->assertRedirect('/admin/biolinks?q=filtered');

        $linkPage = LinkPage::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Filtered page',
            'slug' => 'filtered-page',
            'title' => 'Filtered page',
            'is_published' => true,
        ]);
        $this->actingAs($admin)
            ->delete("/admin/link-pages/{$linkPage->id}?q=filtered")
            ->assertRedirect('/admin/link-pages?q=filtered');
    }

    public function test_nested_deletes_still_require_manage_not_root_delete(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;

        $deleteRole = PlatformRole::query()->create(['name' => 'Bio deleter', 'code' => 'bio_deleter']);
        $deleteRole->permissionGrants()->createMany([
            ['permission' => PlatformPermission::AdminAccess->value],
            ['permission' => PlatformPermission::AdminBioPagesView->value],
            ['permission' => PlatformPermission::AdminBioPagesDelete->value],
        ]);
        $deleter = User::factory()->create();
        $deleter->platformRoles()->attach($deleteRole);

        $first = BioPage::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'handle' => 'root-delete',
            'display_name' => 'Root delete',
            'is_published' => true,
        ]);
        $this->actingAs($deleter)
            ->delete("/admin/biolinks/{$first->id}")
            ->assertRedirect('/admin/biolinks');
        $this->assertDatabaseMissing('bio_pages', ['id' => $first->id]);

        $second = BioPage::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'handle' => 'nested-delete',
            'display_name' => 'Nested delete',
            'is_published' => true,
        ]);
        $bioLink = $second->bioLinks()->create([
            'title' => 'Nested link',
            'destination_url' => 'https://example.com/nested',
            'position' => 0,
            'is_active' => true,
        ]);
        $this->actingAs($deleter)
            ->delete("/admin/biolinks/{$second->id}/links/{$bioLink->id}")
            ->assertForbidden();
        $this->assertDatabaseHas('bio_links', ['id' => $bioLink->id]);

        $manageRole = PlatformRole::query()->create(['name' => 'Bio manager', 'code' => 'bio_manager']);
        $manageRole->permissionGrants()->createMany([
            ['permission' => PlatformPermission::AdminAccess->value],
            ['permission' => PlatformPermission::AdminBioPagesManage->value],
        ]);
        $manager = User::factory()->create();
        $manager->platformRoles()->attach($manageRole);

        $this->actingAs($manager)
            ->delete("/admin/biolinks/{$second->id}/links/{$bioLink->id}")
            ->assertRedirect("/admin/biolinks/{$second->id}");
        $this->assertDatabaseMissing('bio_links', ['id' => $bioLink->id]);
    }

    private function assertLinkRowCapabilities(
        User $viewer,
        string $slug,
        bool $edit,
        bool $delete,
    ): void {
        $this->withoutVite();
        $this->actingAs($viewer)
            ->get('/admin/links?q='.urlencode($slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data', function ($rows) use ($slug, $edit, $delete): bool {
                    $row = $rows->firstWhere('identifier', $slug);
                    PHPUnit::assertIsArray($row);

                    if ($edit) {
                        PHPUnit::assertArrayHasKey('editUrl', $row);
                    } else {
                        PHPUnit::assertArrayNotHasKey('editUrl', $row);
                    }

                    if ($delete) {
                        PHPUnit::assertArrayHasKey('deleteUrl', $row);
                    } else {
                        PHPUnit::assertArrayNotHasKey('deleteUrl', $row);
                    }

                    return true;
                }));
    }
}
