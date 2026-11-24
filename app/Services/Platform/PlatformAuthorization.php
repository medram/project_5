<?php

namespace App\Services\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlatformAuthorization
{
    public function isActive(User $user): bool
    {
        return ! $user->is_suspended && $user->deletion_requested_at === null;
    }

    public function lockSuperAdmin(): void
    {
        DB::table('platform_roles')
            ->where('code', PlatformRoleCode::SuperAdmin->value)
            ->update(['updated_at' => DB::raw('updated_at')]);
    }

    /**
     * Permissions held by assigned roles even while the account is inactive,
     * so an operator cannot edit an account they could not otherwise manage.
     *
     * @return list<string>
     */
    public function assignedAbilities(User $user): array
    {
        $abilities = [];
        $roles = $user->platformRoles()->get();
        $roles->filter(fn (PlatformRole $role) => PlatformRoleCode::tryFrom($role->code) === null)
            ->load('permissionGrants');

        foreach ($roles as $role) {
            foreach ($role->permissions() as $permission) {
                $abilities[$permission->value] = true;
            }
        }

        return array_keys($abilities);
    }

    /** @param list<string> $permissions */
    public function canDelegate(User $actor, array $permissions): bool
    {
        return array_diff($permissions, $this->abilities($actor)) === [];
    }

    /**
     * @return list<PlatformRole>
     */
    public function roles(User $user): array
    {
        if (! $this->isActive($user)) {
            return [];
        }

        $roles = $user->platformRoles()->get();
        $roles->filter(fn (PlatformRole $role) => PlatformRoleCode::tryFrom($role->code) === null)
            ->load('permissionGrants');

        return $roles->all();
    }

    public function hasRole(User $user, PlatformRoleCode $role): bool
    {
        return $this->isActive($user) && $user->hasPlatformRole($role);
    }

    public function allows(User $user, PlatformPermission $permission): bool
    {
        foreach ($this->roles($user) as $role) {
            if (in_array($permission, $role->permissions(), true)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessAdmin(User $user): bool
    {
        return $this->allows($user, PlatformPermission::AdminAccess);
    }

    /**
     * @return list<string>
     */
    public function abilities(User $user): array
    {
        $abilities = [];

        foreach ($this->roles($user) as $role) {
            foreach ($role->permissions() as $permission) {
                $abilities[$permission->value] = true;
            }
        }

        return array_keys($abilities);
    }

    public function isLastActiveSuperAdmin(User $user): bool
    {
        if (! $this->hasRole($user, PlatformRoleCode::SuperAdmin)) {
            return false;
        }

        return User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_suspended', false)
            ->whereNull('deletion_requested_at')
            ->whereHas('platformRoles', fn ($query) => $query->where('code', PlatformRoleCode::SuperAdmin->value))
            ->doesntExist();
    }
}
