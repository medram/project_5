<?php

namespace App\Services\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Models\User;

class PlatformAuthorization
{
    public function isActive(User $user): bool
    {
        return ! $user->is_suspended && $user->deletion_requested_at === null;
    }

    /**
     * @return list<PlatformRoleCode>
     */
    public function roles(User $user): array
    {
        if (! $this->isActive($user)) {
            return [];
        }

        $roles = [];

        foreach ($user->platformRoles()->get() as $role) {
            $roles[] = $role->roleCode();
        }

        return $roles;
    }

    public function hasRole(User $user, PlatformRoleCode $role): bool
    {
        return in_array($role, $this->roles($user), true);
    }

    public function allows(User $user, PlatformPermission $permission): bool
    {
        foreach ($this->roles($user) as $role) {
            if ($role->grants($permission)) {
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
