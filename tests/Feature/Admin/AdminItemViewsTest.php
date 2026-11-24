<?php

namespace Tests\Feature\Admin;

use App\Models\BioPage;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminItemViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_item_views_with_filtered_back_links(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id,
            'slug' => 'admin-view-link',
            'destination_url' => 'https://example.com/visible-destination',
            'access_code' => 'secret-passphrase',
        ]);
        $bioPage = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'handle' => 'admin-view-bio',
            'display_name' => 'Admin view bio',
            'is_published' => true,
        ]);
        $linkPage = LinkPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'name' => 'Admin view page',
            'slug' => 'admin-view-page',
            'title' => 'Admin view page',
            'is_published' => true,
        ]);
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'created_by' => $owner->id,
            'source' => 'campaign',
            'title' => 'Admin view QR',
            'destination_url' => 'https://example.com/qr',
        ]);

        $this->withoutVite()->actingAs($admin);

        $linkQuery = 'q=admin-view&state=active&sort=name&direction=asc';
        $this->get("/admin/links/{$link->id}/insights?{$linkQuery}&days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/link-insights')
                ->where('link.id', $link->id)
                ->where('analytics.days', 7)
                ->where('backUrl', "/admin/links?{$linkQuery}")
                ->where('backLabel', 'Short links')
                ->where('edit.listQuery', "{$linkQuery}&days=7")
                ->where('edit.canManageApprovals', true)
                ->has('edit.folders')
                ->has('edit.customDomains')
                ->has('edit.linkOverlays')
                ->has('edit.linkPages')
                ->has('edit.tagOptions')
                ->has('edit.countries')
                ->where('windowBaseUrl', "/admin/links/{$link->id}/insights?{$linkQuery}")
                ->missing('link.access_code')
                ->missing('link.access_code_version'))
            ->assertDontSee('secret-passphrase');

        $this->get("/admin/biolinks/{$bioPage->id}/insights?q=admin-view&days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/bio-edit')
                ->where('bioPage.id', $bioPage->id)
                ->where('insights.days', 7)
                ->where('backUrl', '/admin/biolinks?q=admin-view')
                ->where('editorUrl', "/admin/biolinks/{$bioPage->id}"));

        $this->get("/admin/link-pages/{$linkPage->id}/insights?state=published&days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/link-page-insights')
                ->where('linkPage.id', $linkPage->id)
                ->where('linkPage.publicUrl', url("/pages/{$linkPage->slug}"))
                ->where('backUrl', '/admin/link-pages?state=published')
                ->where('backLabel', 'Link pages')
                ->where('editUrl', "/admin/link-pages/{$linkPage->id}?state=published")
                ->where('windowBaseUrl', "/admin/link-pages/{$linkPage->id}/insights?state=published")
                ->where('insights.days', 7));

        $this->get("/admin/qr-codes/{$qrCode->id}/insights?q=admin-view&days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/qr-insights')
                ->where('scope', 'campaign')
                ->where('qrCode.id', $qrCode->id)
                ->where('analytics.days', 7)
                ->where('backUrl', '/admin/qr-codes?q=admin-view')
                ->where('backLabel', 'QR codes')
                ->where('editUrl', "/admin/qr-codes/{$qrCode->id}?q=admin-view")
                ->where('windowBaseUrl', "/admin/qr-codes/{$qrCode->id}/insights?q=admin-view"));
    }

    public function test_admin_link_updates_from_insights_return_to_the_insights_page(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id,
            'slug' => 'insights-update',
        ]);

        $this->actingAs($admin)
            ->withHeader('Referer', "/admin/links/{$link->id}/insights?q=insights&days=7")
            ->patch("/admin/links/{$link->id}?q=insights&days=7", [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/updated',
                'title' => 'Updated from insights',
                'slug' => $link->slug,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect("/admin/links/{$link->id}/insights?days=7&q=insights");

        $this->assertSame('https://example.com/updated', $link->fresh()->destination_url);
    }

    public function test_admin_item_views_preserve_the_list_page(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $link = Link::factory()->for($owner)->create(['workspace_id' => $owner->current_workspace_id]);

        $this->withoutVite()->actingAs($admin)
            ->get("/admin/links/{$link->id}/insights?q=page-test&page=3")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/link-insights')
                ->where('backUrl', '/admin/links?q=page-test&page=3')
                ->where('windowBaseUrl', "/admin/links/{$link->id}/insights?q=page-test&page=3"));
    }

    public function test_draft_link_page_insights_do_not_expose_the_public_url(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $linkPage = LinkPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'name' => 'Draft page',
            'slug' => 'admin-draft-page',
            'title' => 'Draft page',
            'is_published' => false,
        ]);

        $this->withoutVite()->actingAs($admin)
            ->get("/admin/link-pages/{$linkPage->id}/insights")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/link-page-insights')
                ->where('linkPage.publicUrl', null));
    }

    public function test_view_only_roles_cannot_open_item_views(): void
    {
        $owner = User::factory()->create();
        $support = User::factory()->support()->create();
        $link = Link::factory()->for($owner)->create(['workspace_id' => $owner->current_workspace_id]);
        $bioPage = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'handle' => 'support-view-bio',
            'display_name' => 'Support view bio',
            'is_published' => true,
        ]);
        $linkPage = LinkPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'name' => 'Support view page',
            'slug' => 'support-view-page',
            'title' => 'Support view page',
            'is_published' => true,
        ]);
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'created_by' => $owner->id,
            'source' => 'campaign',
            'title' => 'Support view QR',
            'destination_url' => 'https://example.com/qr',
        ]);

        $this->actingAs($support);

        $this->get("/admin/links/{$link->id}/insights")->assertForbidden();
        $this->get("/admin/biolinks/{$bioPage->id}/insights")->assertForbidden();
        $this->get("/admin/link-pages/{$linkPage->id}/insights")->assertForbidden();
        $this->get("/admin/qr-codes/{$qrCode->id}/insights")->assertForbidden();
    }

    public function test_admin_content_rows_expose_view_urls_only_to_managers(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $support = User::factory()->support()->create();
        $link = Link::factory()->for($owner)->create(['workspace_id' => $owner->current_workspace_id]);
        $bioPage = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'handle' => 'row-view-bio',
            'display_name' => 'Row view bio',
            'is_published' => true,
        ]);
        $linkPage = LinkPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'name' => 'Row view page',
            'slug' => 'row-view-page',
            'title' => 'Row view page',
            'is_published' => true,
        ]);
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'created_by' => $owner->id,
            'source' => 'campaign',
            'title' => 'Row view QR',
            'destination_url' => 'https://example.com/qr',
        ]);

        $this->withoutVite()->actingAs($admin);

        $this->get('/admin/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.viewUrl', "/admin/links/{$link->id}/insights"));
        $this->get('/admin/biolinks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.viewUrl', "/admin/biolinks/{$bioPage->id}/insights"));
        $this->get('/admin/link-pages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.viewUrl', "/admin/link-pages/{$linkPage->id}/insights"));
        $this->get("/admin/qr-codes?q={$qrCode->code}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.data.0.viewUrl', "/admin/qr-codes/{$qrCode->id}/insights"));

        $this->actingAs($support)->get('/admin/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('rows.data.0.viewUrl')
                ->missing('rows.data.0.editUrl'));
    }

    public function test_admin_editors_keep_insights_and_back_links_inside_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $bioPage = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'handle' => 'editor-view-bio',
            'display_name' => 'Editor view bio',
            'is_published' => true,
        ]);
        $linkPage = LinkPage::query()->create([
            'workspace_id' => $owner->current_workspace_id,
            'user_id' => $owner->id,
            'name' => 'Editor view page',
            'slug' => 'editor-view-page',
            'title' => 'Editor view page',
            'is_published' => true,
        ]);

        $this->withoutVite()->actingAs($admin);

        $this->get("/admin/biolinks/{$bioPage->id}?q=editor-view")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/bio-edit')
                ->where('backUrl', '/admin/biolinks?q=editor-view'));

        $this->get("/admin/link-pages/{$linkPage->id}?q=editor-view")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/link-page-edit')
                ->where('backUrl', '/admin/link-pages?q=editor-view')
                ->where('insightsUrl', "/admin/link-pages/{$linkPage->id}/insights?q=editor-view"));
    }
}
