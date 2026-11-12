<?php

namespace Tests\Feature\Workspaces;

use App\Enums\PlanMetric;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WorkspaceUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_workspace_uses_the_default_free_member_limit(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->firstOrFail();

        $this->assertSame([
            'plan' => 'Free',
            'limit' => 1,
            'members' => 1,
            'pending' => 0,
            'available' => 0,
            'overLimit' => false,
        ], app(WorkspaceUsage::class)->memberCapacityFor($workspace));
    }

    public function test_capacity_uses_workspace_plan_and_only_unexpired_unaccepted_unrevoked_invitations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'pro');
        $workspace->members()->attach($member->id, ['role' => 'member']);
        $this->invitation($workspace, 'pending@example.test', 1, now()->addDay());
        $this->invitation($workspace, 'expired@example.test', 2, now()->subDay());
        $this->invitation($workspace, 'revoked@example.test', 3, now()->addDay(), revokedAt: now());
        $this->invitation($workspace, 'accepted@example.test', 4, now()->addDay(), acceptedAt: now());

        $capacity = app(WorkspaceUsage::class)->memberCapacityFor($workspace);
        $this->assertSame('Pro', $capacity['plan']);
        $this->assertSame(5, $capacity['limit']);
        $this->assertSame(2, $capacity['members']);
        $this->assertSame(1, $capacity['pending']);
        $this->assertSame(2, $capacity['available']);
        $this->assertFalse($capacity['overLimit']);

        app(WorkspaceUsage::class)->assignPlan($workspace, Plan::query()->where('code', 'business')->sole());
        $businessCapacity = app(WorkspaceUsage::class)->memberCapacityFor($workspace->fresh());
        $this->assertNull($businessCapacity['limit']);
        $this->assertNull($businessCapacity['available']);
    }

    public function test_downgrade_keeps_members_and_blocks_new_reservations_when_over_limit(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'pro');
        $workspace->members()->attach($member->id, ['role' => 'member']);
        app(WorkspaceUsage::class)->assignPlan($workspace, Plan::query()->where('code', 'free')->sole());

        $capacity = app(WorkspaceUsage::class)->memberCapacityFor($workspace->fresh());
        $this->assertSame(2, $capacity['members']);
        $this->assertTrue($capacity['overLimit']);
        $this->assertDatabaseHas('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);
        $this->expectException(ValidationException::class);
        app(WorkspaceUsage::class)->assertCanReserveInvitation($workspace->fresh());
    }

    public function test_resource_usage_counts_only_the_requested_workspace(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->firstOrFail();
        $otherWorkspace = $otherOwner->personalWorkspace()->firstOrFail();

        $this->insertLinks($workspace, $owner, 1, 'current');
        $this->insertLinks($otherWorkspace, $otherOwner, 2, 'other');

        $this->assertSame([
            'label' => 'Short links',
            'used' => 1,
            'limit' => 25,
            'overLimit' => false,
        ], app(WorkspaceUsage::class)->metricFor($workspace, PlanMetric::Links));
    }

    public function test_plan_limits_reserve_capacity_per_workspace_not_per_user(): void
    {
        $otherOwner = User::factory()->create();
        $owner = User::factory()->create();
        $otherWorkspace = $otherOwner->personalWorkspace()->firstOrFail();
        $workspace = $owner->personalWorkspace()->firstOrFail();

        $this->insertLinks($otherWorkspace, $otherOwner, 25, 'other');
        $this->insertLinks($workspace, $owner, 24, 'current');

        $created = app(EnforcesPlanLimits::class)->create(
            $workspace,
            PlanMetric::Links,
            function (Workspace $lockedWorkspace) use ($owner): Link {
                $id = $this->insertLink($lockedWorkspace, $owner, 'current-final');

                return Link::query()->findOrFail($id);
            },
        );

        $this->assertSame($workspace->id, $created->workspace_id);
        $this->assertSame(25, $workspace->links()->count());

        $creatorCalled = false;

        try {
            app(EnforcesPlanLimits::class)->create(
                $workspace->fresh(),
                PlanMetric::Links,
                function (Workspace $lockedWorkspace) use ($owner, &$creatorCalled): Link {
                    $creatorCalled = true;
                    $id = $this->insertLink($lockedWorkspace, $owner, 'current-over-limit');

                    return Link::query()->findOrFail($id);
                },
            );

            $this->fail('Expected workspace plan enforcement to reject the creation callback.');
        } catch (ValidationException) {
            $this->assertFalse($creatorCalled);
            $this->assertDatabaseMissing('links', ['slug' => 'current-over-limit']);
        }
    }

    private function insertLinks(Workspace $workspace, User $creator, int $count, string $prefix): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $this->insertLink($workspace, $creator, "{$prefix}-{$index}");
        }
    }

    private function insertLink(Workspace $workspace, User $creator, string $slug): int
    {
        return (int) DB::table('links')->insertGetId([
            'user_id' => $creator->id,
            'workspace_id' => $workspace->id,
            'slug' => $slug,
            'destination_url' => "https://example.test/{$slug}",
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function teamWorkspace(User $owner, string $planCode): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Usage Team',
            'slug' => 'usage-team-'.$owner->id,
            'owner_id' => $owner->id,
            'personal_owner_user_id' => null,
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
        $workspace = Workspace::query()->findOrFail($workspaceId);
        app(WorkspaceUsage::class)->assignPlan(
            $workspace,
            Plan::query()->where('code', $planCode)->sole(),
        );

        return $workspace;
    }

    private function invitation(
        Workspace $workspace,
        string $email,
        int $number,
        \DateTimeInterface $expiresAt,
        ?\DateTimeInterface $acceptedAt = null,
        ?\DateTimeInterface $revokedAt = null,
    ): void {
        DB::table('workspace_invitations')->insert([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'role' => 'member',
            'token_hash' => str_pad((string) $number, 64, '0', STR_PAD_LEFT),
            'expires_at' => $expiresAt,
            'accepted_at' => $acceptedAt,
            'revoked_at' => $revokedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
