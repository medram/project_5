<?php

namespace App\Services\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformRoleAssignments
{
    public function __construct(
        private PlatformAuthorization $authorization,
        private AdminAudit $audit,
    ) {}

    public function grant(User $actor, User $target, PlatformRole $role, ?string $reason = null): bool
    {
        return $this->change($actor, $target, $role, true, $reason);
    }

    public function revoke(User $actor, User $target, PlatformRole $role, ?string $reason = null): bool
    {
        return $this->change($actor, $target, $role, false, $reason);
    }

    public function grantSystem(User $target, PlatformRole $role): bool
    {
        return DB::transaction(function () use ($target, $role): bool {
            $this->authorization->lockSuperAdmin();
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $lockedRole = PlatformRole::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            if (! $this->authorization->isActive($lockedTarget)) {
                throw ValidationException::withMessages(['role' => 'Restore the account before granting a role.']);
            }

            $changed = $lockedTarget->platformRoles()->syncWithoutDetaching([$lockedRole->id]);
            if ($changed['attached'] === []) {
                return false;
            }

            $this->audit->record(null, PlatformPermission::AdminUsersManageRoles, 'grant_role', $lockedTarget, 'console grant', [
                'role' => $lockedRole->code, 'affected_user_count' => 1,
            ]);

            return true;
        });
    }

    private function change(User $actor, User $target, PlatformRole $role, bool $attach, ?string $reason): bool
    {
        return DB::transaction(function () use ($actor, $target, $role, $attach, $reason): bool {
            $this->authorization->lockSuperAdmin();
            $accounts = User::query()
                ->whereIn('id', [$actor->getKey(), $target->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lockedActor = $accounts->get($actor->getKey());
            $lockedTarget = $accounts->get($target->getKey());
            abort_if($lockedActor === null || $lockedTarget === null, 404);
            abort_unless($this->authorization->allows($lockedActor, PlatformPermission::AdminUsersManageRoles), 403);
            if ($lockedActor->is($lockedTarget)) {
                throw ValidationException::withMessages(['role' => 'You may not change your own platform roles.']);
            }

            $lockedRole = PlatformRole::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedRole->code === PlatformRoleCode::SuperAdmin->value) {
                abort_unless($this->authorization->hasRole($lockedActor, PlatformRoleCode::SuperAdmin), 403);
            }
            if ($attach && ! $this->authorization->isActive($lockedTarget)) {
                throw ValidationException::withMessages(['role' => 'Restore the account before granting a role.']);
            }

            $rolePermissions = array_map(fn (PlatformPermission $permission) => $permission->value, $lockedRole->permissions());
            abort_unless($this->authorization->canDelegate($lockedActor, $rolePermissions), 403);
            $resultingPermissions = [];
            if ($this->authorization->isActive($lockedTarget)) {
                foreach ($this->authorization->roles($lockedTarget) as $current) {
                    if (! $attach && $current->is($lockedRole)) {
                        continue;
                    }
                    foreach ($current->permissions() as $permission) {
                        $resultingPermissions[] = $permission->value;
                    }
                }
                if ($attach) {
                    $resultingPermissions = [...$resultingPermissions, ...$rolePermissions];
                }
            }
            abort_unless($this->authorization->canDelegate($lockedActor, $resultingPermissions), 403);

            if (! $attach && $lockedRole->code === PlatformRoleCode::SuperAdmin->value
                && $this->authorization->isLastActiveSuperAdmin($lockedTarget)) {
                throw ValidationException::withMessages(['role' => 'The last active super administrator cannot be changed.']);
            }

            $changed = $attach
                ? $lockedTarget->platformRoles()->syncWithoutDetaching([$lockedRole->id])['attached'] !== []
                : $lockedTarget->platformRoles()->detach($lockedRole->id) > 0;
            if (! $changed) {
                return false;
            }

            $this->audit->record($lockedActor, PlatformPermission::AdminUsersManageRoles, $attach ? 'grant_role' : 'revoke_role', $lockedTarget, $reason, [
                'role' => $lockedRole->code, 'affected_user_count' => 1,
            ]);

            return true;
        });
    }
}
