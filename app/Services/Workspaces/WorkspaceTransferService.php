<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class WorkspaceTransferService
{
    public function __construct(private readonly CreatesPersonalWorkspace $createsPersonalWorkspace) {}

    public function transfer(User $owner, Workspace $workspace, int $newOwnerId, string $password): void
    {
        if (! Hash::check($password, $owner->fresh()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        DB::transaction(function () use ($owner, $workspace, $newOwnerId): void {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            Gate::authorize('transferOwnership', $lockedWorkspace);
            $currentOwnerRole = $this->memberRole($lockedWorkspace, $owner->getKey());
            $newOwnerRole = $this->memberRole($lockedWorkspace, $newOwnerId);

            if ($newOwnerId === (int) $owner->getKey() || $newOwnerRole === null || $newOwnerRole === 'owner') {
                throw ValidationException::withMessages(['user_id' => 'Choose another current workspace member.']);
            }

            if ($currentOwnerRole !== 'owner') {
                abort(403);
            }

            $personalWorkspace = (int) $lockedWorkspace->personal_owner_user_id === (int) $owner->getKey();

            if ($lockedWorkspace->personal_owner_user_id !== null && ! $personalWorkspace) {
                abort(409, 'The personal workspace owner does not match the workspace owner.');
            }

            DB::table('workspace_user')
                ->where('workspace_id', $lockedWorkspace->getKey())
                ->where('user_id', $owner->getKey())
                ->update(['role' => $newOwnerRole, 'updated_at' => now()]);
            DB::table('workspace_user')
                ->where('workspace_id', $lockedWorkspace->getKey())
                ->where('user_id', $newOwnerId)
                ->update(['role' => 'owner', 'updated_at' => now()]);

            if ($personalWorkspace) {
                $lockedWorkspace->forceFill([
                    'owner_id' => $newOwnerId,
                    'personal_owner_user_id' => null,
                    'slug' => "workspace-{$lockedWorkspace->getKey()}",
                ])->save();
                $this->createsPersonalWorkspace->for($owner);
            }

            if (! $personalWorkspace) {
                $lockedWorkspace->forceFill(['owner_id' => $newOwnerId])->save();
            }

            WorkspaceAudit::query()->create([
                'workspace_id' => $lockedWorkspace->getKey(),
                'actor_user_id' => $owner->getKey(),
                'subject_user_id' => $newOwnerId,
                'action' => 'ownership.transferred',
                'metadata' => [
                    'previous_owner_role' => $newOwnerRole,
                    'new_owner_role' => 'owner',
                ],
            ]);
        }, attempts: 3);
    }

    private function memberRole(Workspace $workspace, int $userId): ?string
    {
        return DB::table('workspace_user')
            ->where('workspace_id', $workspace->getKey())
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->value('role');
    }
}
