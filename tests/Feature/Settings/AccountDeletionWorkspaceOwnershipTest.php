<?php

namespace Tests\Feature\Settings;

use App\Models\BioPage;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountDeletionWorkspaceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_member_preserves_shared_workspace_content_and_clears_only_creator_attribution(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $link = Link::factory()->for($member)->create(['workspace_id' => $workspace->id]);
        $folder = Folder::factory()->for($member)->create(['workspace_id' => $workspace->id]);
        $bioPage = BioPage::factory()->for($member)->create(['workspace_id' => $workspace->id]);
        $linkPage = LinkPage::factory()->for($member)->create(['workspace_id' => $workspace->id]);
        $pixel = TrackingPixel::factory()->for($member)->create(['workspace_id' => $workspace->id]);
        $overlay = LinkOverlay::factory()->for($member)->create(['workspace_id' => $workspace->id]);

        $domainId = DB::table('custom_domains')->insertGetId([
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
            'hostname' => "member-{$member->id}.example.test",
            'verification_token' => str_repeat('a', 40),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $webhookId = DB::table('webhooks')->insertGetId([
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
            'name' => 'Member webhook',
            'url' => 'https://hooks.example.test/events',
            'secret' => 'encrypted-test-secret',
            'events' => json_encode(['link.clicked']),
            'revision' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $eventId = DB::table('analytics_events')->insertGetId([
            'uuid' => '00000000-0000-4000-8000-000000000119',
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
            'event_name' => 'link.clicked',
            'occurred_at' => now(),
            'visitor_hash' => str_repeat('b', 64),
            'visitor_key_version' => 'v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($member)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        foreach ([
            [$link, 'links'],
            [$folder, 'folders'],
            [$bioPage, 'bio_pages'],
            [$linkPage, 'link_pages'],
            [$pixel, 'tracking_pixels'],
            [$overlay, 'link_overlays'],
            [$domainId, 'custom_domains'],
            [$webhookId, 'webhooks'],
            [$eventId, 'analytics_events'],
        ] as [$resource, $table]) {
            $id = is_object($resource) ? $resource->id : $resource;
            $this->assertDatabaseHas($table, [
                'id' => $id,
                'workspace_id' => $workspace->id,
                'user_id' => null,
            ]);
        }

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_collaborative_workspace_owner_must_transfer_ownership_before_account_deletion(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($owner)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('workspaces')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($owner->fresh());
        $this->assertSame($owner->id, $workspace->fresh()->owner_id);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_transferred_owner_can_delete_account_without_orphaning_workspace_or_shared_content(): void
    {
        $owner = User::factory()->create(['password' => 'correct-password']);
        $newOwner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($newOwner->id, ['role' => 'member']);
        $link = Link::factory()->for($owner)->create(['workspace_id' => $workspace->id]);

        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/transfer", [
            'password' => 'correct-password',
            'user_id' => $newOwner->id,
        ])->assertRedirect('/account-settings/workspaces');

        $this->assertSame($newOwner->id, $workspace->fresh()->owner_id);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $newOwner->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'member',
        ]);

        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'correct-password'])
            ->assertRedirect(route('home'));

        $this->assertNull($owner->fresh());
        $this->assertSame($newOwner->id, $workspace->fresh()->owner_id);
        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        $this->assertSame(1, DB::table('workspace_user')->where('workspace_id', $workspace->id)->where('role', 'owner')->count());
    }

    private function teamWorkspace(User $owner): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Ownership test team',
            'slug' => 'ownership-team-'.$owner->id,
            'personal_owner_user_id' => null,
            'owner_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Workspace::query()->findOrFail($workspaceId);
    }
}
