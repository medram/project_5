<?php

namespace App\Services\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class PlatformRoleAssignments
{
    public function __construct(
        private PlatformAuthorization $authorization,
        private AdminAudit $audit,
    ) {}

    public function grant(User $actor, User $target, PlatformRoleCode $role, ?string $reason = null): bool
    {
        return $this->change($actor, $target, $role, true, $reason);
    }

    public function revoke(User $actor, User $target, PlatformRoleCode $role, ?string $reason = null): bool
    {
        return $this->change($actor, $target, $role, false, $reason);
    }

    public function grantSystem(User $target, PlatformRoleCode $role): bool
    {
        return DB::transaction(function () use ($target, $role): bool {
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $platformRole = $this->platformRole($role);
            $changed = $lockedTarget->platformRoles()->syncWithoutDetaching([$platformRole->id]);

            if ($changed['attached'] === []) {
                return false;
            }

            $this->audit->record(
                null,
                PlatformPermission::AdminUsersManageRoles,
                'grant_role',
                $lockedTarget,
                'console grant',
                ['role' => $role->value],
            );

            return true;
        });
    }

    private function change(
        User $actor,
        User $target,
        PlatformRoleCode $role,
        bool $attach,
        ?string $reason,
    ): bool {
        return DB::transaction(function () use ($actor, $target, $role, $attach, $reason): bool {
            $lockedActor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                $this->authorization->allows($lockedActor, PlatformPermission::AdminUsersManageRoles),
                403,
            );

            if ($lockedActor->is($lockedTarget)) {
                throw ValidationException::withMessages(['role' => 'You may not change your own platform roles.']);
            }

            $platformRole = $this->platformRole($role);

            if (! $attach && $this->authorization->isLastActiveSuperAdmin($lockedTarget)) {
                throw ValidationException::withMessages(['role' => 'The last active super administrator cannot be changed.']);
            }

            $changed = $attach
                ? $lockedTarget->platformRoles()->syncWithoutDetaching([$platformRole->id])['attached'] !== []
                : $lockedTarget->platformRoles()->detach($platformRole->id) > 0;

            if (! $changed) {
                return false;
            }

            $this->audit->record(
                $lockedActor,
                PlatformPermission::AdminUsersManageRoles,
                $attach ? 'grant_role' : 'revoke_role',
                $lockedTarget,
                $reason,
                ['role' => $role->value],
            );

            return true;
        });
    }

    private function platformRole(PlatformRoleCode $role): PlatformRole
    {
        $platformRole = PlatformRole::query()->where('code', $role->value)->first();

        if ($platformRole === null) {
            throw new LogicException("Platform role [{$role->value}] is not seeded.");
        }

        return $platformRole;
    }
}
