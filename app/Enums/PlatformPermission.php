<?php

namespace App\Enums;

enum PlatformPermission: string
{
    case AdminAccess = 'admin.access';
    case AdminSearch = 'admin.search';
    case AdminInsightsView = 'admin.insights.view';
    case AdminUsersView = 'admin.users.view';
    case AdminUsersManageStatus = 'admin.users.manage_status';
    case AdminUsersManageVerification = 'admin.users.manage_verification';
    case AdminUsersManageRoles = 'admin.users.manage_roles';
    case AdminUsersScheduleDeletion = 'admin.users.schedule_deletion';
    case AdminNotificationsBroadcast = 'admin.notifications.broadcast';
    case AdminBillingView = 'admin.billing.view';
    case AdminBillingManage = 'admin.billing.manage';
    case AdminContentViewMetadata = 'admin.content.view_metadata';
    case AdminSettingsView = 'admin.settings.view';
    case AdminSettingsManage = 'admin.settings.manage';
    case AdminFilesView = 'admin.files.view';
    case AdminFilesManage = 'admin.files.manage';
    case AdminPagesManage = 'admin.pages.manage';
    case AdminLocalizationsManage = 'admin.localizations.manage';
    case AdminLogsView = 'admin.logs.view';
    case AdminLogsExport = 'admin.logs.export';

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Access administration',
            self::AdminSearch => 'Search platform metadata',
            self::AdminInsightsView => 'View platform insights',
            self::AdminUsersView => 'View accounts',
            self::AdminUsersManageStatus => 'Manage account status',
            self::AdminUsersManageVerification => 'Manage email verification',
            self::AdminUsersManageRoles => 'Manage platform roles',
            self::AdminUsersScheduleDeletion => 'Schedule/restore account deletion',
            self::AdminNotificationsBroadcast => 'Broadcast notifications',
            self::AdminBillingView => 'View billing',
            self::AdminBillingManage => 'Manage billing',
            self::AdminContentViewMetadata => 'View content metadata',
            self::AdminSettingsView => 'View platform settings',
            self::AdminSettingsManage => 'Manage platform settings',
            self::AdminFilesView => 'View file metadata',
            self::AdminFilesManage => 'Manage file retention',
            self::AdminPagesManage => 'Manage custom pages',
            self::AdminLocalizationsManage => 'Manage localizations',
            self::AdminLogsView => 'View operational logs',
            self::AdminLogsExport => 'Export operational logs',
        };
    }
}
