<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRoleCode;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly PlatformAuthorization $authorization) {}

    public function __invoke(Request $request): Response
    {
        $search = trim((string) ($request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ])['q'] ?? ''));
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $usersQuery = User::query()->with([
            'personalWorkspace' => fn ($workspace) => $workspace
                ->withCount(['links', 'bioPages'])
                ->with('legacySubscription.plan:id,code,name'),
        ]);

        if ($search !== '') {
            $usersQuery->where(fn ($users) => $users
                ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('personalWorkspace.legacySubscription.plan', fn ($plan) => $plan
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("code LIKE ? ESCAPE '!'", [$pattern])));
        }

        $usersQuery->with('platformRoles');

        $accounts = [];

        foreach ($usersQuery->orderBy('id')->get() as $user) {
            /** @var User $user */
            $workspace = $user->personalWorkspace;

            $accounts[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
                'createdAt' => $user->created_at?->toISOString(),
                'isAdmin' => $this->authorization->hasRole($user, PlatformRoleCode::SuperAdmin),
                'roles' => $user->platformRoles->map(fn ($role): array => [
                    'code' => $role->code,
                    'name' => $role->name,
                ])->values()->all(),
                'isSuspended' => $user->is_suspended,
                'suspendedAt' => $user->suspended_at?->toISOString(),
                'personalWorkspaceName' => $workspace?->name,
                'subscription' => [
                    'status' => $workspace?->legacySubscription?->status,
                    'planCode' => $workspace?->legacySubscription?->plan?->code,
                    'planName' => $workspace?->legacySubscription?->plan?->name,
                ],
                'counts' => [
                    'links' => $workspace === null ? 0 : $workspace->links_count,
                    'bioPages' => $workspace === null ? 0 : $workspace->bio_pages_count,
                ],
            ];
        }

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get(['code', 'name'])
            ->map(fn (Plan $plan): array => [
                'code' => $plan->code,
                'name' => $plan->name,
            ])
            ->all();

        return Inertia::render('admin/index', [
            'overview' => [
                'accounts' => User::query()->count(),
                'administrators' => User::query()->whereHas('platformRoles', fn ($roles) => $roles->where('code', PlatformRoleCode::SuperAdmin->value))->count(),
                'suspendedAccounts' => User::query()->where('is_suspended', true)->count(),
                'shortLinks' => Link::query()->count(),
            ],
            'plans' => $plans,
            'search' => $search,
            'users' => $accounts,
        ]);
    }
}
