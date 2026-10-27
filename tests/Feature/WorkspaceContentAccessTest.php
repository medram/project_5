<?php

namespace Tests\Feature;

use App\Models\BioPage;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceContentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_member_can_manage_another_members_bio_and_link_pages(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);
        $page = BioPage::factory()->for($owner)->create(['workspace_id' => $workspace->id]);
        $linkPage = LinkPage::factory()->for($owner)->create(['workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $this->withoutVite();

        $this->actingAs($member)->get('/dashboard/biolinks')->assertOk()->assertInertia(fn ($page) => $page->has('bioPages', 1));
        $this->actingAs($member)->get('/dashboard/link-pages')->assertOk()->assertInertia(fn ($page) => $page->has('linkPages', 1));
        $this->actingAs($member)->patch("/dashboard/biolinks/{$page->id}", [
            'handle' => $page->handle, 'display_name' => 'Updated', 'bio' => null, 'is_published' => false,
        ])->assertRedirect();
        $this->actingAs($member)->patch("/dashboard/link-pages/{$linkPage->id}", [
            'name' => $linkPage->name, 'slug' => $linkPage->slug, 'title' => $linkPage->title,
            'description' => $linkPage->description, 'is_published' => false,
        ])->assertRedirect();
        $this->assertDatabaseHas('bio_pages', ['id' => $page->id, 'display_name' => 'Updated']);
    }

    public function test_nonmember_cannot_view_workspace_content_and_foreign_children_are_rejected(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $page = LinkPage::factory()->for($owner)->create(['workspace_id' => $workspace->id]);
        $item = $page->items()->create(['title' => 'Item', 'destination_url' => 'https://example.test', 'position' => 0]);
        $this->actingAs($outsider)->get("/dashboard/link-pages/{$page->id}")->assertNotFound();
        $this->actingAs($outsider)->patch("/dashboard/link-pages/{$page->id}/items/{$item->id}", [
            'title' => 'Forged', 'destination_url' => 'https://example.test', 'is_active' => true,
        ])->assertNotFound();
    }

    public function test_workspace_members_cannot_manage_pixel_or_overlay_records(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);
        $pixel = TrackingPixel::factory()->for($owner)->create(['workspace_id' => $workspace->id]);
        $overlay = LinkOverlay::factory()->for($owner)->create(['workspace_id' => $workspace->id]);
        app(WorkspaceContext::class)->activate($member, $workspace);

        $this->actingAs($member)->post('/dashboard/pixels', [
            'name' => 'Blocked pixel', 'provider' => 'meta_pixel', 'provider_id' => '123456789012345', 'is_active' => true,
        ])->assertForbidden();
        $this->actingAs($member)->patch("/dashboard/pixels/{$pixel->id}", [
            'name' => $pixel->name, 'provider' => $pixel->provider, 'provider_id' => $pixel->provider_id, 'is_active' => false,
        ])->assertForbidden();
        $this->actingAs($member)->post("/dashboard/pixels/{$pixel->id}/rules", [
            'target_type' => 'link_overlay',
            'target_id' => $overlay->id,
            'trigger' => 'button_click',
        ])->assertForbidden();
        $this->actingAs($member)->delete("/dashboard/pixels/{$pixel->id}")->assertForbidden();
        $this->actingAs($member)->post('/dashboard/link-overlays', [
            'name' => 'Blocked overlay', 'heading' => 'Heading', 'cta_label' => 'Continue', 'is_active' => true,
        ])->assertForbidden();
        $this->actingAs($member)->patch("/dashboard/link-overlays/{$overlay->id}", [
            'name' => $overlay->name, 'heading' => $overlay->heading, 'body' => $overlay->body,
            'cta_label' => $overlay->cta_label, 'is_active' => false,
        ])->assertForbidden();
        $this->actingAs($member)->delete("/dashboard/link-overlays/{$overlay->id}")->assertForbidden();
        app(WorkspaceContext::class)->activate($admin, $workspace);
        $this->actingAs($admin)->post("/dashboard/pixels/{$pixel->id}/rules", [
            'target_type' => 'link_overlay',
            'target_id' => $overlay->id,
            'trigger' => 'button_click',
        ])->assertRedirect('/dashboard/pixels');
        $this->actingAs($admin)->patch("/dashboard/pixels/{$pixel->id}", [
            'name' => 'Admin pixel', 'provider' => $pixel->provider, 'provider_id' => $pixel->provider_id, 'is_active' => false,
        ])->assertRedirect('/dashboard/pixels');
        $this->actingAs($admin)->patch("/dashboard/link-overlays/{$overlay->id}", [
            'name' => 'Admin overlay', 'heading' => $overlay->heading, 'body' => $overlay->body,
            'cta_label' => $overlay->cta_label, 'is_active' => false,
        ])->assertRedirect('/dashboard/link-overlays');
    }

    public function test_route_bound_resources_must_belong_to_the_active_workspace(): void
    {
        $member = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $workspaces = app(WorkspaceContext::class);
        $activeWorkspace = $workspaces->currentFor($member);
        $foreignWorkspace = $workspaces->currentFor($foreignOwner);
        $foreignWorkspace->members()->attach($member->id, ['role' => 'admin']);
        $bioPage = BioPage::factory()->for($foreignOwner)->create(['workspace_id' => $foreignWorkspace->id]);
        $bioLink = $bioPage->bioLinks()->create([
            'title' => 'Foreign bio link',
            'destination_url' => 'https://example.test',
            'position' => 0,
            'is_active' => true,
        ]);
        $linkPage = LinkPage::factory()->for($foreignOwner)->create(['workspace_id' => $foreignWorkspace->id]);
        $item = $linkPage->items()->create([
            'title' => 'Foreign item',
            'destination_url' => 'https://example.test',
            'position' => 0,
            'is_active' => true,
        ]);
        $pixel = TrackingPixel::factory()->for($foreignOwner)->create(['workspace_id' => $foreignWorkspace->id]);
        $overlay = LinkOverlay::factory()->for($foreignOwner)->create(['workspace_id' => $foreignWorkspace->id]);
        $workspaces->activate($member, $activeWorkspace);

        $this->actingAs($member)->get("/dashboard/biolinks/{$bioPage->id}")->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/biolinks/{$bioPage->id}", [
            'handle' => $bioPage->handle, 'display_name' => 'Forged', 'is_published' => false,
        ])->assertNotFound();
        $this->actingAs($member)->delete("/dashboard/biolinks/{$bioPage->id}")->assertNotFound();
        $this->actingAs($member)->get("/dashboard/link-pages/{$linkPage->id}")->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/link-pages/{$linkPage->id}", [
            'name' => $linkPage->name, 'slug' => $linkPage->slug, 'title' => $linkPage->title,
            'is_published' => false,
        ])->assertNotFound();
        $this->actingAs($member)->delete("/dashboard/link-pages/{$linkPage->id}")->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/link-pages/{$linkPage->id}/items/{$item->id}", [
            'title' => 'Forged item', 'destination_url' => 'https://example.test', 'is_active' => true,
        ])->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/biolinks/{$bioPage->id}/links/{$bioLink->id}", [
            'title' => 'Forged bio link', 'destination_url' => 'https://example.test', 'is_active' => true,
        ])->assertNotFound();
        $this->actingAs($member)->delete("/dashboard/biolinks/{$bioPage->id}/links/{$bioLink->id}")->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/biolinks/{$bioPage->id}/links/reorder", [
            'link_ids' => [$bioLink->id],
        ])->assertNotFound();
        $this->actingAs($member)->post("/dashboard/link-pages/{$linkPage->id}/items", [
            'title' => 'Forged item', 'destination_url' => 'https://example.test', 'is_active' => true,
        ])->assertNotFound();
        $this->actingAs($member)->delete("/dashboard/link-pages/{$linkPage->id}/items/{$item->id}")->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/link-pages/{$linkPage->id}/items/reorder", [
            'item_ids' => [$item->id],
        ])->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/pixels/{$pixel->id}", [
            'name' => $pixel->name, 'provider' => $pixel->provider, 'provider_id' => $pixel->provider_id, 'is_active' => false,
        ])->assertNotFound();
        $this->actingAs($member)->delete("/dashboard/pixels/{$pixel->id}")->assertNotFound();
        $this->actingAs($member)->get("/dashboard/link-overlays/{$overlay->id}")->assertNotFound();
        $this->actingAs($member)->patch("/dashboard/link-overlays/{$overlay->id}", [
            'name' => $overlay->name, 'heading' => $overlay->heading, 'cta_label' => $overlay->cta_label,
            'is_active' => false,
        ])->assertNotFound();
        $this->actingAs($member)->delete("/dashboard/link-overlays/{$overlay->id}")->assertNotFound();
    }
}
