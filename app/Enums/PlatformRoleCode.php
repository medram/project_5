<?php

namespace App\Enums;

enum PlatformRoleCode: string
{
    case SuperAdmin = 'super_admin';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrator',
            self::Support => 'Support',
        };
    }

    /**
     * @return list<PlatformPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PlatformPermission::cases(),
            self::Support => [
                PlatformPermission::AdminAccess,
                PlatformPermission::AdminSearch,
                PlatformPermission::AdminInsightsView,
                PlatformPermission::AdminUsersView,
                PlatformPermission::AdminUsersManageStatus,
                PlatformPermission::AdminContentViewMetadata,
                PlatformPermission::AdminFilesView,
                PlatformPermission::AdminLogsView,
            ],
        };
    }

    public function grants(PlatformPermission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}
