<?php

namespace Tests\Feature\Admin;

use App\Models\BioPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BioAdminEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_edits_and_reorders_only_the_selected_bio_pages_children(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $page = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'handle' => 'bio-admin-scope', 'display_name' => 'Profile', 'is_published' => true,
        ]);
        $other = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'handle' => 'bio-admin-other', 'display_name' => 'Other', 'is_published' => true,
        ]);
        $link = $page->bioLinks()->create(['title' => 'Site', 'destination_url' => 'https://example.com', 'position' => 0, 'is_active' => true]);
        $foreign = $other->bioLinks()->create(['title' => 'Other', 'destination_url' => 'https://example.com', 'position' => 0, 'is_active' => true]);
        $actorWorkspace = $admin->current_workspace_id;
        $this->withoutVite()->actingAs($admin)->get("/admin/biolinks/{$page->id}")
            ->assertOk()->assertInertia(fn (Assert $props) => $props->component('admin/content/bio-edit')->where('bioPage.id', $page->id));
        $this->post("/admin/biolinks/{$page->id}/blocks", ['type' => 'text', 'text' => 'Hello', 'is_active' => true])
            ->assertSessionHasNoErrors()->assertRedirect("/admin/biolinks/{$page->id}");
        $block = $page->contentBlocks()->firstOrFail();
        $this->patch("/admin/biolinks/{$page->id}/content/reorder", ['items' => [
            ['type' => 'block', 'id' => $block->id], ['type' => 'link', 'id' => $link->id],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(0, $block->fresh()->position);
        $this->assertSame(1, $link->fresh()->position);
        $this->patch("/admin/biolinks/{$page->id}/links/{$foreign->id}", ['title' => 'Wrong'])->assertNotFound();
        $this->assertSame('Other', $foreign->fresh()->title);
        $this->assertSame($actorWorkspace, $admin->fresh()->current_workspace_id);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'permission' => 'admin.content.biolinks.manage', 'subject_id' => $page->id]);
    }

    public function test_support_and_other_users_cannot_open_bio_editor_or_preview(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::query()->create([
            'workspace_id' => $owner->current_workspace_id, 'user_id' => $owner->id,
            'handle' => 'bio-admin-private', 'display_name' => 'Private', 'is_published' => false,
        ]);
        $block = $page->contentBlocks()->create(['type' => 'image', 'content' => ['alt_text' => ''], 'position' => 0, 'is_active' => true]);
        foreach ([User::factory()->support()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get("/admin/biolinks/{$page->id}")->assertForbidden();
            $this->get("/admin/biolinks/{$page->id}/blocks/{$block->id}/image")->assertForbidden();
        }
    }
}
