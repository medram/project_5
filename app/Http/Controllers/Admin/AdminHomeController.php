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

        if ($authorization->allows($user, PlatformPermission::AdminUsersView)) {
            return to_route('admin.users.index');
        }

        if ($authorization->allows($user, PlatformPermission::AdminUsersManageRoles)) {
            return to_route('admin.roles.index');
        }

        abort(403);
    }
}
