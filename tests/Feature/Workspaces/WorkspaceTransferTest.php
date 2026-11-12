<?php

namespace Tests\Feature\Workspaces;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_transfer_requires_password_and_exchanges_existing_member_roles_atomically(): void
    {
        $owner = User::factory()->create(['password' => 'correct-password']);
        $newOwner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($newOwner->id, ['role' => 'member']);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/transfer", [
                'password' => 'wrong-password',
                'user_id' => $newOwner->id,
            ])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/transfer", [
                'password' => 'correct-password',
                'user_id' => $newOwner->id,
            ])
            ->assertRedirect('/account-settings/workspaces');

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
        $this->assertSame(1, DB::table('workspace_user')->where('workspace_id', $workspace->id)->where('role', 'owner')->count());
        $this->assertDatabaseHas('workspace_audits', [
            'workspace_id' => $workspace->id,
            'actor_user_id' => $owner->id,
            'subject_user_id' => $newOwner->id,
            'action' => 'ownership.transferred',
        ]);
        $audit = DB::table('workspace_audits')->where('workspace_id', $workspace->id)->where('action', 'ownership.transferred')->sole();
        $this->assertSame('member', json_decode($audit->metadata, true)['previous_owner_role']);
    }

    public function test_non_owner_or_non_member_cannot_receive_ownership(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);

        $this->actingAs($admin)
            ->post("/workspaces/{$workspace->id}/transfer", [
                'password' => 'password',
                'user_id' => $owner->id,
            ])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/transfer", [
                'password' => 'password',
                'user_id' => $outsider->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_transferring_a_personal_workspace_preserves_each_users_personal_workspace(): void
    {
        $owner = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->firstOrFail();
        app(WorkspaceUsage::class)->assignPlan($workspace, Plan::query()->where('code', 'pro')->sole());
        $workspace->members()->attach($newOwner->id, ['role' => 'member']);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/transfer", [
                'password' => 'password',
                'user_id' => $newOwner->id,
            ])
            ->assertRedirect('/account-settings/workspaces');

        $this->assertNull($workspace->fresh()->personal_owner_user_id);
        $this->assertNotNull($owner->fresh()->personalWorkspace()->first());
        $this->assertSame($newOwner->id, $newOwner->fresh()->personalWorkspace()->first()->personal_owner_user_id);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $newOwner->id,
            'role' => 'owner',
        ]);
    }

    private function teamWorkspace(User $owner): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Transfer Team',
            'slug' => 'transfer-team-'.$owner->id,
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
