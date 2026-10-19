<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $users = User::query()
            ->with('subscription.plan:id,code,name')
            ->withCount(['links', 'bioPages'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'emailVerifiedAt' => $user->email_verified_at?->toISOString(),
                'createdAt' => $user->created_at?->toISOString(),
                'isAdmin' => $user->is_admin,
                'isSuspended' => $user->is_suspended,
                'suspendedAt' => $user->suspended_at?->toISOString(),
                'subscription' => [
                    'status' => $user->subscription?->status,
                    'planCode' => $user->subscription?->plan?->code,
                    'planName' => $user->subscription?->plan?->name,
                ],
                'counts' => [
                    'links' => $user->links_count,
                    'bioPages' => $user->bio_pages_count,
                ],
            ])
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
            'users' => $users,
        ]);
    }
}
