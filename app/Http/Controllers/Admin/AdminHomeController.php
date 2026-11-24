<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminHomeController extends Controller
{
    public function __invoke(Request $request, PlatformAuthorization $authorization): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $destinations = [
            [PlatformPermission::AdminUsersView, '/admin/users'],
            [PlatformPermission::AdminUsersManageRoles, '/admin/roles'],
            [PlatformPermission::AdminInsightsView, '/admin/insights'],
            [PlatformPermission::AdminLinksView, '/admin/links'],
            [PlatformPermission::AdminBioPagesView, '/admin/biolinks'],
            [PlatformPermission::AdminLinkPagesView, '/admin/link-pages'],
            [PlatformPermission::AdminQrCodesView, '/admin/qr-codes'],
            [PlatformPermission::AdminFoldersView, '/admin/folders'],
            [PlatformPermission::AdminDomainsView, '/admin/custom-domains'],
            [PlatformPermission::AdminOverlaysView, '/admin/link-overlays'],
            [PlatformPermission::AdminPixelsView, '/admin/pixels'],
            [PlatformPermission::AdminPagesManage, '/admin/custom-pages'],
            [PlatformPermission::AdminFilesView, '/admin/files'],
            [PlatformPermission::AdminBillingView, '/admin/plans'],
            [PlatformPermission::AdminSettingsView, '/admin/settings'],
            [PlatformPermission::AdminLocalizationsManage, '/admin/localizations'],
            [PlatformPermission::AdminLogsView, '/admin/logs'],
        ];
        $abilities = $authorization->abilities($user);
        foreach ($destinations as [$permission, $destination]) {
            if (in_array($permission->value, $abilities, true)) {
                return redirect($destination);
            }
        }

        abort(403);
    }
}
