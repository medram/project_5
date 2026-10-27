<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $search = trim((string) ($request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ])['q'] ?? ''));
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        $usersQuery = User::query()->with([
            'personalWorkspace' => fn ($workspace) => $workspace
                ->withCount(['links', 'bioPages'])
                ->with('subscription.plan:id,code,name'),
        ]);

        if ($search !== '') {
            $usersQuery->where(fn ($users) => $users
                ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('personalWorkspace.subscription.plan', fn ($plan) => $plan
                    ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("code LIKE ? ESCAPE '!'", [$pattern])));
        }

        $users = $usersQuery->orderBy('id')
            ->get()
            ->map(function (User $user): array {
                $workspace = $user->personalWorkspace;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
                    'createdAt' => $user->created_at?->toISOString(),
                    'isAdmin' => $user->is_admin,
                    'isSuspended' => $user->is_suspended,
                    'suspendedAt' => $user->suspended_at?->toISOString(),
                    'personalWorkspaceName' => $workspace?->name,
                    'subscription' => [
                        'status' => $workspace?->subscription?->status,
                        'planCode' => $workspace?->subscription?->plan?->code,
                        'planName' => $workspace?->subscription?->plan?->name,
                    ],
                    'counts' => [
                        'links' => $workspace?->links_count ?? 0,
                        'bioPages' => $workspace?->bio_pages_count ?? 0,
                    ],
                ];
            })
            ->all();

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
                'administrators' => User::query()->where('is_admin', true)->count(),
                'suspendedAccounts' => User::query()->where('is_suspended', true)->count(),
                'shortLinks' => Link::query()->count(),
            ],
            'plans' => $plans,
            'search' => $search,
            'users' => $users,
        ]);
    }
}
