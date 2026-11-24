<?php

namespace App\Services\Platform;

use App\Http\Middleware\ScopedAdminContent;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminContentAccess
{
    public function authorize(Request $request, string $ability, Model $subject): void
    {
        $scope = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);
        if (is_array($scope)) {
            $root = $scope['root'];
            $workspace = $scope['workspace'];
            if (in_array($ability, ['view', 'update', 'delete'], true) && $root->is($subject)) {
                return;
            }
            if ($subject instanceof Workspace && $workspace->is($subject)
                && in_array($ability, ['selectIntegrations', 'manageIntegrations', 'manageLinkApprovals'], true)) {
                return;
            }
        }

        Gate::authorize($ability, $subject);
    }
}
