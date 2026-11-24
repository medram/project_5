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
    case AdminUsersManageProfile = 'admin.users.manage_profile';
    case AdminUsersScheduleDeletion = 'admin.users.schedule_deletion';
    case AdminNotificationsBroadcast = 'admin.notifications.broadcast';
    case AdminBillingView = 'admin.billing.view';
    case AdminBillingManage = 'admin.billing.manage';
    case AdminLinksView = 'admin.content.links.view';
    case AdminLinksManage = 'admin.content.links.manage';
    case AdminLinksDelete = 'admin.content.links.delete';
    case AdminBioPagesView = 'admin.content.biolinks.view';
    case AdminBioPagesManage = 'admin.content.biolinks.manage';
    case AdminBioPagesDelete = 'admin.content.biolinks.delete';
    case AdminQrCodesView = 'admin.content.qr_codes.view';
    case AdminQrCodesManage = 'admin.content.qr_codes.manage';
    case AdminQrCodesDelete = 'admin.content.qr_codes.delete';
    case AdminLinkPagesView = 'admin.content.link_pages.view';
    case AdminLinkPagesManage = 'admin.content.link_pages.manage';
    case AdminLinkPagesDelete = 'admin.content.link_pages.delete';
    case AdminFoldersView = 'admin.content.folders.view';
    case AdminFoldersManage = 'admin.content.folders.manage';
    case AdminFoldersDelete = 'admin.content.folders.delete';
    case AdminDomainsView = 'admin.content.domains.view';
    case AdminDomainsManage = 'admin.content.domains.manage';
    case AdminDomainsDelete = 'admin.content.domains.delete';
    case AdminOverlaysView = 'admin.content.overlays.view';
    case AdminOverlaysManage = 'admin.content.overlays.manage';
    case AdminOverlaysDelete = 'admin.content.overlays.delete';
    case AdminPixelsView = 'admin.content.pixels.view';
    case AdminPixelsManage = 'admin.content.pixels.manage';
    case AdminPixelsDelete = 'admin.content.pixels.delete';
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
            self::AdminUsersManageProfile => 'Edit account profiles',
            self::AdminUsersScheduleDeletion => 'Schedule/restore account deletion',
            self::AdminNotificationsBroadcast => 'Broadcast notifications',
            self::AdminBillingView => 'View billing',
            self::AdminBillingManage => 'Manage billing',
            self::AdminLinksView => 'View short-link metadata',
            self::AdminLinksManage => 'Edit short links',
            self::AdminLinksDelete => 'Delete short links',
            self::AdminBioPagesView => 'View bio-page metadata',
            self::AdminBioPagesManage => 'Edit bio pages',
            self::AdminBioPagesDelete => 'Delete bio pages',
            self::AdminQrCodesView => 'View QR-code metadata',
            self::AdminQrCodesManage => 'Edit QR codes',
            self::AdminQrCodesDelete => 'Delete QR codes',
            self::AdminLinkPagesView => 'View link-page metadata',
            self::AdminLinkPagesManage => 'Edit link pages',
            self::AdminLinkPagesDelete => 'Delete link pages',
            self::AdminFoldersView => 'View folder metadata',
            self::AdminFoldersManage => 'Edit folders',
            self::AdminFoldersDelete => 'Delete folders',
            self::AdminDomainsView => 'View domain metadata',
            self::AdminDomainsManage => 'Edit domains',
            self::AdminDomainsDelete => 'Delete custom domains',
            self::AdminOverlaysView => 'View overlay metadata',
            self::AdminOverlaysManage => 'Edit overlays',
            self::AdminOverlaysDelete => 'Delete CTA overlays',
            self::AdminPixelsView => 'View pixel metadata',
            self::AdminPixelsManage => 'Edit pixels',
            self::AdminPixelsDelete => 'Delete tracking pixels',
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
