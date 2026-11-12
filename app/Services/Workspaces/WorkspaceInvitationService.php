<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAudit;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Notifications\WorkspaceNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceInvitationService
{
    public function __construct(private readonly WorkspaceUsage $usage) {}

    /** @return array{invitation: WorkspaceInvitation, token: string} */
    public function issue(User $actor, Workspace $workspace, string $email, string $role): array
    {
        return $this->createOrRotate($actor, $workspace, $email, $role);
    }

    /** @return array{invitation: WorkspaceInvitation, token: string} */
    public function resend(User $actor, Workspace $workspace, WorkspaceInvitation $invitation): array
    {
        if ((int) $invitation->workspace_id !== (int) $workspace->getKey()) {
            abort(404);
        }

        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            abort(410, 'This invitation can no longer be resent.');
        }

        return $this->createOrRotate($actor, $workspace, $invitation->email, $invitation->role, $invitation->getKey());
    }

    public function revoke(User $actor, Workspace $workspace, WorkspaceInvitation $invitation): void
    {
        DB::transaction(function () use ($actor, $workspace, $invitation): void {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            Gate::authorize('manageMembers', $lockedWorkspace);
            $lockedInvitation = $lockedWorkspace->invitations()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $actorRole = DB::table('workspace_user')
                ->where('workspace_id', $lockedWorkspace->getKey())
                ->where('user_id', $actor->getKey())
                ->value('role');
            if ($actorRole === 'admin' && $lockedInvitation->role === 'admin') {
                abort(403);
            }

            if ($lockedInvitation->accepted_at !== null) {
                abort(409, 'An accepted invitation cannot be revoked.');
            }

            if ($lockedInvitation->revoked_at === null) {
                $lockedInvitation->forceFill(['revoked_at' => now()])->save();
                $this->audit($lockedWorkspace, $actor, null, 'invitation.revoked', [
                    'invitation_id' => $lockedInvitation->getKey(),
                    'email' => $lockedInvitation->email,
                ]);
            }
        }, attempts: 3);
    }

    public function deliver(User $actor, Workspace $workspace, WorkspaceInvitation $invitation, string $token): string
    {
        $url = route('invitations.show', ['token' => $token]);
        Notification::route('mail', $invitation->email)->notify(new WorkspaceInvitationNotification(
            workspaceName: $workspace->name,
            inviterName: $actor->name,
            role: $invitation->role,
            url: $url,
            expiresAt: $invitation->expires_at,
        ));

        $invitation->forceFill(['sent_at' => now()])->save();

        return $url;
    }

    public function findForAcceptance(User $user, string $token): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        $this->assertInvitee($user, $invitation);

        if ($invitation->revoked_at !== null || ($invitation->accepted_at === null && ! $invitation->expires_at->isFuture())) {
            abort(410, 'This invitation is no longer active.');
        }

        if ($invitation->accepted_at !== null && (int) $invitation->accepted_by_user_id !== (int) $user->getKey()) {
            abort(410, 'This invitation has already been used.');
        }

        return $invitation;
    }

    public function accept(User $user, string $token): Workspace
    {
        $tokenHash = hash('sha256', $token);
        $invitationId = WorkspaceInvitation::query()->where('token_hash', $tokenHash)->value('id');

        if ($invitationId === null) {
            abort(404);
        }

        $workspaceId = WorkspaceInvitation::query()->whereKey($invitationId)->value('workspace_id');
        $accepted = false;
        $workspace = DB::transaction(function () use ($user, $tokenHash, $invitationId, $workspaceId, &$accepted): Workspace {
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspaceId);
            $invitation = WorkspaceInvitation::query()
                ->whereKey($invitationId)
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertInvitee($user, $invitation);

            if ($invitation->accepted_at !== null) {
                if ((int) $invitation->accepted_by_user_id === (int) $user->getKey()) {
                    return $workspace;
                }

                abort(410, 'This invitation has already been used.');
            }

            if ($invitation->revoked_at !== null || ! $invitation->expires_at->isFuture()) {
                abort(410, 'This invitation is no longer active.');
            }

            if (DB::table('workspace_user')
                ->where('workspace_id', $workspace->getKey())
                ->where('user_id', $user->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'invitation' => 'You are already a member of this workspace.',
                ]);
            }

            $capacity = $this->usage->memberCapacityFor($workspace);

            if ($capacity['limit'] !== null && $capacity['members'] >= $capacity['limit']) {
                throw ValidationException::withMessages([
                    'plan' => "The {$capacity['plan']} plan has reached its workspace member limit.",
                ]);
            }

            $workspace->members()->attach($user->getKey(), ['role' => $invitation->role]);
            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_by_user_id' => $user->getKey(),
            ])->save();
            $this->audit($workspace, $user, $user, 'invitation.accepted', [
                'invitation_id' => $invitation->getKey(),
                'role' => $invitation->role,
            ]);
            app(WorkspaceContext::class)->activate($user, $workspace);
            $accepted = true;

            return $workspace;
        }, attempts: 3);

        if ($accepted) {
            app(WorkspaceNotificationService::class)->notifyManagers(
                $workspace,
                'invitations',
                'Workspace invitation accepted',
                'A new member accepted an invitation to your workspace.',
                'workspace',
                (int) $workspace->getKey(),
            );
        }

        return $workspace;
    }

    /** @return array{invitation: WorkspaceInvitation, token: string} */
    private function createOrRotate(
        User $actor,
        Workspace $workspace,
        string $email,
        string $role,
        ?int $invitationId = null,
    ): array {
        $email = mb_strtolower(trim($email));
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($actor, $workspace, $email, $role, $invitationId, $token): WorkspaceInvitation {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            Gate::authorize('manageMembers', $lockedWorkspace);
            $actorRole = DB::table('workspace_user')
                ->where('workspace_id', $lockedWorkspace->getKey())
                ->where('user_id', $actor->getKey())
                ->value('role');

            if (! in_array($role, ['admin', 'member'], true)) {
                throw ValidationException::withMessages(['role' => 'Choose admin or member.']);
            }

            if ($actorRole === 'admin' && $role !== 'member') {
                abort(403);
            }

            if (DB::table('workspace_user')
                ->join('users', 'users.id', '=', 'workspace_user.user_id')
                ->where('workspace_user.workspace_id', $lockedWorkspace->getKey())
                ->whereRaw('LOWER(users.email) = ?', [$email])
                ->exists()) {
                throw ValidationException::withMessages(['email' => 'This person is already a workspace member.']);
            }

            $query = $lockedWorkspace->invitations()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->orderByDesc('id')
                ->lockForUpdate();
            $existing = $invitationId === null
                ? $query->first()
                : $lockedWorkspace->invitations()->whereKey($invitationId)->lockForUpdate()->firstOrFail();
            if (
                $actorRole === 'admin'
                && $existing !== null
                && $existing->role === 'admin'
                && $existing->expires_at->isFuture()
            ) {
                abort(403);
            }

            if ($invitationId !== null && ($existing->accepted_at !== null || $existing->revoked_at !== null)) {
                abort(410, 'This invitation can no longer be resent.');
            }

            $createsCapacity = $existing === null || ! $existing->expires_at->isFuture();
            $capacityBefore = $createsCapacity
                ? $this->usage->memberCapacityFor($lockedWorkspace)
                : null;

            if ($createsCapacity) {
                $this->usage->assertCanReserveInvitation($lockedWorkspace);
            }

            $existing ??= new WorkspaceInvitation;
            $existing->fill([
                'workspace_id' => $lockedWorkspace->getKey(),
                'invited_by_user_id' => $actor->getKey(),
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
                'accepted_by_user_id' => null,
                'revoked_at' => null,
                'sent_at' => null,
            ])->save();
            if ($capacityBefore !== null && $capacityBefore['limit'] !== null) {
                $usedBefore = $capacityBefore['members'] + $capacityBefore['pending'];
                $capacityAfter = $this->usage->memberCapacityFor($lockedWorkspace);
                $usedAfter = $capacityAfter['members'] + $capacityAfter['pending'];

                if ($usedBefore < $capacityBefore['limit'] && $usedAfter >= $capacityBefore['limit']) {
                    app(WorkspaceNotificationService::class)->notifyManagers(
                        $lockedWorkspace,
                        'usage',
                        'Workspace member limit reached',
                        'Pending invitations now use the available workspace member seats.',
                        'workspace',
                        (int) $lockedWorkspace->getKey(),
                    );
                }
            }
            $this->audit($lockedWorkspace, $actor, null, 'invitation.issued', [
                'invitation_id' => $existing->getKey(),
                'email' => $email,
                'role' => $role,
                'expires_at' => $existing->expires_at->toIso8601String(),
            ]);

            return $existing;
        }, attempts: 3);

        return ['invitation' => $invitation, 'token' => $token];
    }

    private function assertInvitee(User $user, WorkspaceInvitation $invitation): void
    {
        if ($user->is_suspended || ! hash_equals(mb_strtolower($invitation->email), mb_strtolower($user->email))) {
            abort(403);
        }
    }

    /** @param array<string, int|string> $metadata */
    private function audit(Workspace $workspace, User $actor, ?User $subject, string $action, array $metadata): void
    {
        WorkspaceAudit::query()->create([
            'workspace_id' => $workspace->getKey(),
            'actor_user_id' => $actor->getKey(),
            'subject_user_id' => $subject?->getKey(),
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
