<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_admin_and_member_capabilities_follow_the_workspace_role_matrix(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        foreach ([$owner, $admin, $member] as $workspaceMember) {
            $this->assertTrue($workspaceMember->can('view', $workspace));
            $this->assertTrue($workspaceMember->can('manageContent', $workspace));
            $this->assertTrue($workspaceMember->can('selectIntegrations', $workspace));
            $this->assertTrue($workspaceMember->can('viewBilling', $workspace));
        }

        foreach ([$owner, $admin] as $integrationManager) {
            $this->assertTrue($integrationManager->can('manageIntegrations', $workspace));
            $this->assertTrue($integrationManager->can('manageMembers', $workspace));
        }

        $this->assertTrue($owner->can('manageBilling', $workspace));
        $this->assertTrue($owner->can('transferOwnership', $workspace));
        $this->assertFalse($admin->can('manageBilling', $workspace));
        $this->assertFalse($admin->can('transferOwnership', $workspace));
        $this->assertFalse($member->can('manageIntegrations', $workspace));
        $this->assertFalse($member->can('manageMembers', $workspace));
        $this->assertFalse($member->can('manageBilling', $workspace));
        $this->assertFalse($member->can('transferOwnership', $workspace));
        $this->assertFalse($outsider->can('view', $workspace));
        $this->assertFalse($outsider->can('manageContent', $workspace));
        $this->assertFalse($outsider->can('selectIntegrations', $workspace));
    }

    private function teamWorkspace(User $owner): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Role Matrix',
            'slug' => 'role-matrix-'.$owner->id,
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
