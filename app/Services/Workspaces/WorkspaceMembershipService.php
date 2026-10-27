<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkspaceMembershipService
{
    public function changeRole(User $actor, Workspace $workspace, User $member, string $role): void
    {
        if (! in_array($role, ['admin', 'member'], true)) {
            throw ValidationException::withMessages(['role' => 'Choose admin or member.']);
        }

        DB::transaction(function () use ($actor, $workspace, $member, $role): void {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            Gate::authorize('manageMembers', $lockedWorkspace);
            $actorRole = $this->lockMemberRole($lockedWorkspace, $actor);
            $memberRole = $this->lockMemberRole($lockedWorkspace, $member);

            if ($memberRole === 'owner') {
                abort(403, 'Transfer ownership to change the owner.');
            }

            if ($actorRole === 'admin' && ($memberRole !== 'member' || $role !== 'member')) {
                abort(403);
            }

            if ($memberRole === $role) {
                return;
            }

            DB::table('workspace_user')
                ->where('workspace_id', $lockedWorkspace->getKey())
                ->where('user_id', $member->getKey())
                ->update(['role' => $role, 'updated_at' => now()]);
            WorkspaceAudit::query()->create([
                'workspace_id' => $lockedWorkspace->getKey(),
                'actor_user_id' => $actor->getKey(),
                'subject_user_id' => $member->getKey(),
                'action' => 'member.role_changed',
                'metadata' => ['from' => $memberRole, 'to' => $role],
            ]);
        }, attempts: 3);
    }

    public function remove(User $actor, Workspace $workspace, User $member): void
    {
        DB::transaction(function () use ($actor, $workspace, $member): void {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            Gate::authorize('manageMembers', $lockedWorkspace);
            $actorRole = $this->lockMemberRole($lockedWorkspace, $actor);
            $memberRole = $this->lockMemberRole($lockedWorkspace, $member);

            if ($memberRole === 'owner') {
                abort(403, 'The workspace owner cannot be removed.');
            }

            if ($actorRole === 'admin' && $memberRole !== 'member') {
                abort(403);
            }

            WorkspaceAudit::query()->create([
                'workspace_id' => $lockedWorkspace->getKey(),
                'actor_user_id' => $actor->getKey(),
                'subject_user_id' => $member->getKey(),
                'action' => 'member.removed',
                'metadata' => ['role' => $memberRole],
            ]);
            DB::table('workspace_user')
                ->where('workspace_id', $lockedWorkspace->getKey())
                ->where('user_id', $member->getKey())
                ->delete();

            if ((int) $member->current_workspace_id === (int) $lockedWorkspace->getKey()) {
                app(WorkspaceContext::class)->currentFor($member->fresh());
            }
        }, attempts: 3);
    }

    private function lockMemberRole(Workspace $workspace, User $user): string
    {
        $role = DB::table('workspace_user')
            ->where('workspace_id', $workspace->getKey())
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->value('role');

        if ($role === null) {
            abort(404);
        }

        return $role;
    }
}
