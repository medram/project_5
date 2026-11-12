<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformRole;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminRoleController extends Controller
{
    public function __invoke(): Response
    {
        $roles = PlatformRole::query()
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(function (PlatformRole $role): array {
                return [
                    'code' => $role->code,
                    'name' => $role->name,
                    'usersCount' => (int) $role->users_count,
                    'permissions' => array_map(
                        fn ($permission): array => [
                            'value' => $permission->value,
                            'label' => $permission->label(),
                        ],
                        $role->roleCode()->permissions(),
                    ),
                    'members' => $role->users()
                        ->orderBy('users.name')
                        ->orderBy('users.id')
                        ->limit(8)
                        ->get(['users.id', 'users.name', 'users.email'])
                        ->map(fn (User $user): array => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ])->all(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
        ]);
    }
}
