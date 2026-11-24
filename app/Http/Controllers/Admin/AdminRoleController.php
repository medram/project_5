<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Http\Controllers\Controller;
use App\Models\PlatformRole;
use App\Models\User;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminRoleController extends Controller
{
    public function __construct(
        private readonly PlatformAuthorization $authorization,
        private readonly AdminAudit $audit,
    ) {}

    public function __invoke(Request $request): Response
    {
        $roles = PlatformRole::query()->withCount('users')->orderBy('id')->get();
        $roles->filter(fn (PlatformRole $role) => PlatformRoleCode::tryFrom($role->code) === null)
            ->load('permissionGrants');
        $actorRoles = $request->user()->platformRoles()->pluck('code')->all();
        $roles = $roles->map(function (PlatformRole $role) use ($actorRoles): array {
                return [
                    'code' => $role->code,
                    'name' => $role->name,
                    'usersCount' => (int) $role->users_count,
                    'isBuiltIn' => PlatformRoleCode::tryFrom($role->code) !== null,
                    'isAssignedToActor' => in_array($role->code, $actorRoles, true),
                    'permissions' => $this->presentPermissions($role->permissions()),
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
            'allPermissions' => $this->presentPermissions(PlatformPermission::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:40', 'regex:/\A[a-z0-9_]{1,40}\z/', Rule::notIn(['none', 'super_admin', 'support']), Rule::unique('platform_roles', 'code')],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::enum(PlatformPermission::class)],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $this->authorization->lockSuperAdmin();
            $actor = $this->lockedActor($request);
            abort_unless($this->authorization->canDelegate($actor, $validated['permissions']), 403);

            if (PlatformRole::query()->where('code', $validated['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'This role code is already in use.']);
            }

            $role = PlatformRole::query()->create(['name' => $validated['name'], 'code' => $validated['code']]);
            $role->permissionGrants()->createMany(array_map(fn (string $permission) => ['permission' => $permission], $validated['permissions']));
            $this->audit->record($actor, PlatformPermission::AdminUsersManageRoles, 'create_role', $role, null, ['role' => $role->code, 'affected_user_count' => 0]);
            $this->auditPermissions($actor, $role, $validated['permissions'], [], 0);
        });

        return back();
    }

    public function update(Request $request, PlatformRole $platformRole): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['prohibited'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::enum(PlatformPermission::class)],
        ]);

        DB::transaction(function () use ($request, $platformRole, $validated): void {
            $this->authorization->lockSuperAdmin();
            $actor = $this->lockedActor($request);
            $role = PlatformRole::query()->whereKey($platformRole->getKey())->lockForUpdate()->firstOrFail();
            abort_if(PlatformRoleCode::tryFrom($role->code) !== null, 403);
            abort_if($role->users()->whereKey($actor->getKey())->exists(), 403);
            $before = array_map(fn (PlatformPermission $permission) => $permission->value, $role->permissions());
            abort_unless($this->authorization->canDelegate($actor, [...$before, ...$validated['permissions']]), 403);

            $added = array_values(array_diff($validated['permissions'], $before));
            $removed = array_values(array_diff($before, $validated['permissions']));
            $members = $role->users()->count();
            if ($role->name === $validated['name'] && $added === [] && $removed === []) {
                return;
            }

            $role->update(['name' => $validated['name']]);
            $role->permissionGrants()->whereIn('permission', $removed)->delete();
            $role->permissionGrants()->createMany(array_map(fn (string $permission) => ['permission' => $permission], $added));
            $this->audit->record($actor, PlatformPermission::AdminUsersManageRoles, 'update_role', $role, null, ['role' => $role->code, 'affected_user_count' => $members]);
            $this->auditPermissions($actor, $role, $added, $removed, $members);
        });

        return back();
    }

    public function destroy(Request $request, PlatformRole $platformRole): RedirectResponse
    {
        DB::transaction(function () use ($request, $platformRole): void {
            $this->authorization->lockSuperAdmin();
            $actor = $this->lockedActor($request);
            $role = PlatformRole::query()->whereKey($platformRole->getKey())->lockForUpdate()->firstOrFail();
            abort_if(PlatformRoleCode::tryFrom($role->code) !== null, 403);
            $permissions = array_map(fn (PlatformPermission $permission) => $permission->value, $role->permissions());
            abort_unless($this->authorization->canDelegate($actor, $permissions), 403);
            $members = $role->users()->count();
            if ($members !== 0) {
                throw ValidationException::withMessages(['role' => 'Remove all assigned accounts before deleting this role.']);
            }

            $this->audit->record($actor, PlatformPermission::AdminUsersManageRoles, 'delete_role', $role, null, ['role' => $role->code, 'affected_user_count' => 0]);
            $this->auditPermissions($actor, $role, [], $permissions, 0);
            $role->delete();
        });

        return back();
    }

    private function lockedActor(Request $request): User
    {
        $actor = User::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
        abort_unless($this->authorization->allows($actor, PlatformPermission::AdminUsersManageRoles), 403);

        return $actor;
    }

    /** @param list<PlatformPermission> $permissions */
    private function presentPermissions(array $permissions): array
    {
        return array_map(fn (PlatformPermission $permission) => ['value' => $permission->value, 'label' => $permission->label()], $permissions);
    }

    /** @param list<string> $added @param list<string> $removed */
    private function auditPermissions(User $actor, PlatformRole $role, array $added, array $removed, int $members): void
    {
        foreach (['add_role_permission' => $added, 'remove_role_permission' => $removed] as $action => $permissions) {
            foreach ($permissions as $permission) {
                $this->audit->record($actor, PlatformPermission::AdminUsersManageRoles, $action, $role, null, [
                    'role' => $role->code, 'permission' => $permission, 'affected_user_count' => $members,
                ]);
            }
        }
    }
}
