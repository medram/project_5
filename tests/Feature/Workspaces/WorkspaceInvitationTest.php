<?php

namespace Tests\Feature\Workspaces;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use App\Services\Workspaces\WorkspaceInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_admin_or_member_but_admin_can_only_invite_members(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $this->addMember($workspace, $admin, 'admin');
        $this->addMember($workspace, $member, 'member');

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'new-admin@example.test', 'role' => 'admin'])
            ->assertRedirect()
            ->assertSessionHas('invitationUrl');
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'new-admin@example.test',
            'role' => 'admin',
        ]);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'new-member@example.test', 'role' => 'member'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'admin-invite@example.test', 'role' => 'member'])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'forbidden-admin@example.test', 'role' => 'admin'])
            ->assertForbidden();
        $this->actingAs($member)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'forbidden-member@example.test', 'role' => 'member'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'forbidden-owner@example.test', 'role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('workspace_invitations', ['email' => 'forbidden-admin@example.test']);
        $this->assertDatabaseMissing('workspace_invitations', ['email' => 'forbidden-member@example.test']);
    }

    public function test_admin_cannot_revoke_or_replace_an_owner_issued_admin_invitation(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $this->addMember($workspace, $admin, 'admin');

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", [
                'email' => 'new-admin@example.test',
                'role' => 'admin',
            ])
            ->assertRedirect();
        $invitation = DB::table('workspace_invitations')->where('email', 'new-admin@example.test')->sole();

        $this->actingAs($admin)
            ->delete("/workspaces/{$workspace->id}/invitations/{$invitation->id}")
            ->assertForbidden();
        $this->actingAs($admin)
            ->post("/workspaces/{$workspace->id}/invitations", [
                'email' => 'new-admin@example.test',
                'role' => 'member',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('workspace_invitations', [
            'id' => $invitation->id,
            'role' => 'admin',
            'token_hash' => $invitation->token_hash,
            'revoked_at' => null,
        ]);
    }

    public function test_invitation_url_is_hashed_expires_in_seven_days_and_resend_or_replacement_rotates_it(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'person@example.test', 'role' => 'member'])
            ->assertRedirect();
        $firstUrl = session('invitationUrl');
        $firstToken = basename(parse_url($firstUrl, PHP_URL_PATH));
        $invitation = DB::table('workspace_invitations')->where('workspace_id', $workspace->id)->sole();

        $this->assertSame(hash('sha256', $firstToken), $invitation->token_hash);
        $this->assertSame(now()->addDays(7)->toDateTimeString(), $invitation->expires_at);

        Notification::assertSentOnDemand(
            WorkspaceInvitationNotification::class,
            function (WorkspaceInvitationNotification $notification, array $channels) use ($firstUrl): bool {
                $this->assertSame(['mail'], $channels);
                $this->assertSame($firstUrl, $notification->url);

                return true;
            },
        );
        $this->assertNotSame($firstToken, $invitation->token_hash);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations/{$invitation->id}/resend")
            ->assertRedirect();
        $secondUrl = session('invitationUrl');
        $secondToken = basename(parse_url($secondUrl, PHP_URL_PATH));
        $this->assertNotSame($firstToken, $secondToken);
        $this->assertDatabaseHas('workspace_invitations', [
            'id' => $invitation->id,
            'token_hash' => hash('sha256', $secondToken),
        ]);
        $this->assertDatabaseCount('workspace_invitations', 1);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => ' PERSON@example.test ', 'role' => 'admin'])
            ->assertRedirect();
        $replacementUrl = session('invitationUrl');
        $replacementToken = basename(parse_url($replacementUrl, PHP_URL_PATH));
        $this->assertNotSame($secondToken, $replacementToken);
        $this->assertDatabaseHas('workspace_invitations', [
            'id' => $invitation->id,
            'email' => 'person@example.test',
            'role' => 'admin',
            'token_hash' => hash('sha256', $replacementToken),
        ]);
        $this->assertDatabaseCount('workspace_invitations', 1);
    }

    public function test_resend_rechecks_invitation_state_after_a_stale_read(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'accepted@example.test']);
        $workspace = $this->teamWorkspace($owner);
        $tokenHash = hash('sha256', 'original-token');
        $invitationId = DB::table('workspace_invitations')->insertGetId([
            'workspace_id' => $workspace->id,
            'invited_by_user_id' => $owner->id,
            'email' => $invitee->email,
            'role' => 'member',
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDay(),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $staleInvitation = WorkspaceInvitation::query()->findOrFail($invitationId);
        DB::table('workspace_invitations')->where('id', $invitationId)->update([
            'accepted_at' => now(),
            'accepted_by_user_id' => $invitee->id,
            'updated_at' => now(),
        ]);

        try {
            $this->actingAs($owner);
            app(WorkspaceContext::class)->activate($owner, $workspace);
            app(WorkspaceInvitationService::class)->resend($owner, $workspace, $staleInvitation);
            $this->fail('An accepted invitation must not be rotated from a stale model.');
        } catch (HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }

        $this->assertDatabaseHas('workspace_invitations', [
            'id' => $invitationId,
            'token_hash' => $tokenHash,
            'accepted_by_user_id' => $invitee->id,
        ]);
    }

    public function test_invitation_acceptance_is_email_bound_suspension_aware_and_idempotent(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'joiner@example.test', 'role' => 'member'])
            ->assertRedirect();
        $token = basename(parse_url(session('invitationUrl'), PHP_URL_PATH));

        $wrongEmailUser = User::factory()->create(['email' => 'wrong@example.test']);
        $this->actingAs($wrongEmailUser)
            ->post("/invitations/{$token}/accept")
            ->assertForbidden();
        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id, 'user_id' => $wrongEmailUser->id]);

        $suspended = User::factory()->create(['email' => 'joiner@example.test', 'is_suspended' => true]);
        $this->actingAs($suspended)
            ->post("/invitations/{$token}/accept")
            ->assertForbidden();

        $suspended->forceFill(['is_suspended' => false])->save();
        $invitee = $suspended->fresh();
        $this->actingAs($invitee)
            ->post("/invitations/{$token}/accept")
            ->assertRedirect('/account-settings/workspaces');
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'accepted_by_user_id' => $invitee->id,
        ]);
        $this->assertSame($workspace->id, DB::table('users')->where('id', $invitee->id)->value('current_workspace_id'));

        $this->actingAs($invitee)
            ->post("/invitations/{$token}/accept")
            ->assertRedirect('/account-settings/workspaces');
        $this->assertSame(1, DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $invitee->id)
            ->count());
    }

    public function test_expired_revoked_and_already_member_invitations_cannot_be_accepted(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'expired@example.test', 'role' => 'member'])
            ->assertRedirect();
        $expiredToken = basename(parse_url(session('invitationUrl'), PHP_URL_PATH));
        $this->travel(8)->days();
        $expiredUser = User::factory()->create(['email' => 'expired@example.test']);
        $this->actingAs($expiredUser)
            ->post("/invitations/{$expiredToken}/accept")
            ->assertStatus(410);

        $this->travelBack();
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'revoked@example.test', 'role' => 'member'])
            ->assertRedirect();
        $revokedToken = basename(parse_url(session('invitationUrl'), PHP_URL_PATH));
        $revokedInvitation = DB::table('workspace_invitations')
            ->where('email', 'revoked@example.test')
            ->sole();
        $this->actingAs($owner)
            ->delete("/workspaces/{$workspace->id}/invitations/{$revokedInvitation->id}")
            ->assertRedirect();
        $revokedUser = User::factory()->create(['email' => 'revoked@example.test']);
        $this->actingAs($revokedUser)
            ->post("/invitations/{$revokedToken}/accept")
            ->assertStatus(410);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => $owner->email, 'role' => 'member'])
            ->assertSessionHasErrors('email');
    }

    public function test_member_capacity_reserves_unexpired_invitations_and_releases_revoked_slots(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'pro');
        $members = User::factory()->count(3)->create();
        foreach ($members as $member) {
            $this->addMember($workspace, $member, 'member');
        }

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'reserved@example.test', 'role' => 'member'])
            ->assertRedirect();
        $invitation = DB::table('workspace_invitations')->where('email', 'reserved@example.test')->sole();

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'over-capacity@example.test', 'role' => 'member'])
            ->assertSessionHasErrors('plan');

        $this->actingAs($owner)
            ->delete("/workspaces/{$workspace->id}/invitations/{$invitation->id}")
            ->assertRedirect();
        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'freed-slot@example.test', 'role' => 'member'])
            ->assertRedirect();
    }

    public function test_invitation_listing_is_workspace_scoped_and_never_exposes_token_hash(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $otherWorkspace = $this->teamWorkspace($otherOwner);

        $this->actingAs($owner)
            ->post("/workspaces/{$workspace->id}/invitations", ['email' => 'listed@example.test', 'role' => 'member'])
            ->assertRedirect();
        $this->actingAs($otherOwner)
            ->post("/workspaces/{$otherWorkspace->id}/invitations", ['email' => 'private@example.test', 'role' => 'member'])
            ->assertRedirect();
        $tokenHash = DB::table('workspace_invitations')->where('email', 'listed@example.test')->value('token_hash');

        $this->actingAs($owner)
            ->get("/workspaces/{$workspace->id}/invitations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'listed@example.test')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertDontSee($tokenHash);

        $this->actingAs($owner)
            ->get("/workspaces/{$otherWorkspace->id}/invitations")
            ->assertForbidden();

        $member = User::factory()->create();
        $this->addMember($workspace, $member, 'member');
        $this->actingAs($member)
            ->get("/workspaces/{$workspace->id}/invitations")
            ->assertForbidden()
            ->assertDontSee('listed@example.test')
            ->assertDontSee('pending');
    }

    public function test_workspace_settings_hide_invitation_metadata_from_members_but_show_it_to_managers(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $this->addMember($workspace, $admin, 'admin');
        $this->addMember($workspace, $member, 'member');
        $this->actingAs($owner)->post("/workspaces/{$workspace->id}/invitations", [
            'email' => 'private-invitee@example.test', 'role' => 'member',
        ])->assertRedirect();

        $this->actingAs($member)
            ->post("/workspaces/{$workspace->id}/switch")
            ->assertRedirect('/dashboard/links');
        $this->get('/account-settings/workspaces')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('invitations')
                ->where('canManageMembers', false));
        foreach ([$owner, $admin] as $manager) {
            $this->actingAs($manager)
                ->post("/workspaces/{$workspace->id}/switch")
                ->assertRedirect('/dashboard/links');
            $this->get('/account-settings/workspaces')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->has('invitations', 1)
                    ->where('invitations.0.email', 'private-invitee@example.test')
                    ->where('invitations.0.status', 'pending'));
        }
    }

    private function teamWorkspace(User $owner, string $planCode = 'pro'): Workspace
    {
        $now = now();
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Product Team',
            'slug' => 'product-team-'.$owner->id,
            'personal_owner_user_id' => null,
            'owner_id' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $workspace = Workspace::query()->findOrFail($workspaceId);
        app(WorkspaceUsage::class)->assignPlan(
            $workspace,
            Plan::query()->where('code', $planCode)->sole(),
        );

        return $workspace;
    }

    private function addMember(Workspace $workspace, User $user, string $role): void
    {
        $workspace->members()->attach($user->id, ['role' => $role]);
    }
}
