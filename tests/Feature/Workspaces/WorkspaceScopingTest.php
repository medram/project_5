<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_a_personal_workspace_and_active_workspace_pointer(): void
    {
        $user = User::factory()->create();
        $workspace = DB::table('workspaces')
            ->where('personal_owner_user_id', $user->id)
            ->sole();

        $this->assertSame("personal-{$user->id}", $workspace->slug);
        $this->assertSame($workspace->id, DB::table('users')->where('id', $user->id)->value('current_workspace_id'));
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'plan_id' => DB::table('plans')->where('code', 'free')->value('id'),
            'status' => 'active',
            'source' => 'manual',
        ]);
    }

    public function test_users_can_create_and_activate_a_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/workspaces', ['name' => 'Product Team'])
            ->assertRedirect('/account-settings/workspaces');

        $workspace = DB::table('workspaces')->where('name', 'Product Team')->sole();
        $this->assertSame($workspace->id, DB::table('users')->where('id', $user->id)->value('current_workspace_id'));
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'plan_id' => DB::table('plans')->where('code', 'free')->value('id'),
            'status' => 'active',
            'source' => 'manual',
        ]);
    }

    public function test_workspace_settings_expose_only_the_signed_in_users_memberships_and_current_team(): void
    {
        $user = User::factory()->create(['name' => 'Current User']);
        $teamOwner = User::factory()->create(['name' => 'Team Owner']);
        $outsider = User::factory()->create(['name' => 'Outside User']);
        $teamWorkspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Current Team',
            'slug' => 'current-team',
            'personal_owner_user_id' => null,
            'owner_id' => $teamOwner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workspace_user')->insert([
            [
                'workspace_id' => $teamWorkspaceId,
                'user_id' => $user->id,
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => $teamWorkspaceId,
                'user_id' => $teamOwner->id,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('users')->where('id', $user->id)->update(['current_workspace_id' => $teamWorkspaceId]);
        $outsideWorkspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Private Team',
            'slug' => 'private-team',
            'personal_owner_user_id' => null,
            'owner_id' => $outsider->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $outsideWorkspaceId,
            'user_id' => $outsider->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutVite();

        $this->actingAs($user->fresh())
            ->get('/account-settings/workspaces')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/workspaces/index')
                ->where('workspace.current.id', $teamWorkspaceId)
                ->where('workspace.current.role', 'admin')
                ->has('workspace.current.plan')
                ->where('workspace.current.usage.links.used', 0)
                ->has('workspace.current.usage.links.limit')
                ->has('workspace.memberships', 2)
                ->has('members', 2)
                ->where('members.0.email', $user->email)
                ->where('canManageMembers', true)
                ->where('memberCapacity.limit', 1)
                ->missing('workspace.memberships.2')
            )
            ->assertDontSee('Private Team');
    }

    public function test_workspace_switching_requires_membership_and_invalid_pointer_repairs_to_personal(): void
    {
        $user = User::factory()->create();
        $personalWorkspace = DB::table('workspaces')->where('personal_owner_user_id', $user->id)->sole();
        $now = now();
        $workspaceOwner = User::factory()->create();
        $teamWorkspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Marketing',
            'slug' => 'marketing',
            'personal_owner_user_id' => null,
            'owner_id' => $workspaceOwner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $teamWorkspaceId,
            'user_id' => $workspaceOwner->id,
            'role' => 'owner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $teamWorkspaceId,
            'user_id' => $user->id,
            'role' => 'member',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($user)
            ->from('/dashboard/links/999')
            ->post("/workspaces/{$teamWorkspaceId}/switch")
            ->assertRedirect(route('links.index'));

        $switchMethods = Route::getRoutes()->getByName('workspaces.switch')->methods();
        $this->assertContains('POST', $switchMethods);
        $this->assertNotContains('PATCH', $switchMethods);
        $this->assertSame($teamWorkspaceId, DB::table('users')->where('id', $user->id)->value('current_workspace_id'));

        $otherUser = User::factory()->create();
        $otherWorkspaceId = DB::table('workspaces')->where('personal_owner_user_id', $otherUser->id)->value('id');
        $this->actingAs($user)
            ->post("/workspaces/{$otherWorkspaceId}/switch")
            ->assertForbidden();
        $this->assertSame($teamWorkspaceId, DB::table('users')->where('id', $user->id)->value('current_workspace_id'));

        DB::table('users')->where('id', $user->id)->update(['current_workspace_id' => $otherWorkspaceId]);
        $currentWorkspace = app(WorkspaceContext::class)->currentFor($user->fresh());

        $this->assertSame($personalWorkspace->id, $currentWorkspace->id);
        $this->assertSame($personalWorkspace->id, DB::table('users')->where('id', $user->id)->value('current_workspace_id'));
    }
}
