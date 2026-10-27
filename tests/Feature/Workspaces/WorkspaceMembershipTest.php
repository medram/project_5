<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_change_and_remove_non_owners_but_cannot_change_or_remove_themself(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);
        DB::table('users')->where('id', $member->id)->update(['current_workspace_id' => $workspace->id]);

        $this->actingAs($owner)
            ->patch("/workspaces/{$workspace->id}/members/{$member->id}", ['role' => 'admin'])
            ->assertRedirect();
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'admin',
        ]);

        $this->actingAs($owner)
            ->patch("/workspaces/{$workspace->id}/members/{$owner->id}", ['role' => 'member'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete("/workspaces/{$workspace->id}/members/{$owner->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete("/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertRedirect();
        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);
        $this->assertSame(
            DB::table('workspaces')->where('personal_owner_user_id', $member->id)->value('id'),
            DB::table('users')->where('id', $member->id)->value('current_workspace_id'),
        );
        $this->assertDatabaseHas('workspace_audits', [
            'workspace_id' => $workspace->id,
            'actor_user_id' => $owner->id,
            'subject_user_id' => $member->id,
            'action' => 'member.removed',
        ]);
    }

    public function test_admin_can_manage_members_only_and_cannot_change_admin_or_owner_roles(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($admin)
            ->patch("/workspaces/{$workspace->id}/members/{$member->id}", ['role' => 'admin'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch("/workspaces/{$workspace->id}/members/{$admin->id}", ['role' => 'member'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete("/workspaces/{$workspace->id}/members/{$admin->id}")
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete("/workspaces/{$workspace->id}/members/{$owner->id}")
            ->assertForbidden();
        $this->actingAs($admin)
            ->delete("/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertRedirect();
    }

    public function test_member_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->patch("/workspaces/{$workspace->id}/members/{$owner->id}", ['role' => 'member'])
            ->assertForbidden();
        $this->actingAs($member)
            ->delete("/workspaces/{$workspace->id}/members/{$owner->id}")
            ->assertForbidden();
    }

    private function teamWorkspace(User $owner): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Membership Team',
            'slug' => 'membership-team-'.$owner->id,
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
