<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRoleCode;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\User;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserIndexController extends Controller
{
    public function __construct(private readonly WorkspaceUsage $usage) {}

    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'deletion_pending'])],
            'verification' => ['nullable', Rule::in(['verified', 'unverified'])],
            'role' => ['nullable', Rule::in([...array_map(fn (PlatformRoleCode $role): string => $role->value, PlatformRoleCode::cases()), 'none'])],
            'plan' => ['nullable', 'string', Rule::exists('plans', 'code')->where('is_active', true)],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $query = User::query()
            ->with([
                'avatarMedia:id,public_id',
                'platformRoles:id,code,name',
                'personalWorkspace' => fn ($workspace) => $workspace
                    ->withCount(['links', 'bioPages', 'members', 'customDomains'])
                    ->with('legacySubscription.plan:id,code,name'),
            ])
            ->withCount('workspaces');

        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn (Builder $users) => $users
                ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]));
        }

        $status = $filters['status'] ?? null;

        if ($status === 'active') {
            $query->where('is_suspended', false)->whereNull('deletion_requested_at');
        } elseif ($status === 'suspended') {
            $query->where('is_suspended', true);
        } elseif ($status === 'deletion_pending') {
            $query->whereNotNull('deletion_requested_at');
        }

        $verification = $filters['verification'] ?? null;

        if ($verification === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verification === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        $role = $filters['role'] ?? null;

        if ($role === 'none') {
            $query->doesntHave('platformRoles');
        } elseif ($role !== null) {
            $query->whereHas('platformRoles', fn (Builder $roles) => $roles->where('code', $role));
        }

        $planCode = $filters['plan'] ?? null;

        if ($planCode !== null) {
            $plan = Plan::query()->where('code', $planCode)->where('is_active', true)->sole();
            $query->whereHas('personalWorkspace', function (Builder $workspace) use ($plan): void {
                $workspace->where(function (Builder $matches) use ($plan): void {
                    $matches
                        ->whereHas('legacySubscription.plan', fn (Builder $legacy) => $legacy->whereKey($plan->id))
                        ->orWhereHas('planOverrides', fn (Builder $override) => $override
                            ->whereNull('revoked_at')
                            ->where('plan_id', $plan->id))
                        ->orWhereHas('subscriptions', fn (Builder $subscription) => $subscription
                            ->whereIn('stripe_price', array_filter([
                                $plan->stripe_monthly_price_id,
                                $plan->stripe_yearly_price_id,
                            ])));
                });
            });
        }

        $users = $query
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (User $user): array {
                $workspace = $user->personalWorkspace;
                $effective = $workspace === null ? null : $this->usage->effectivePlanFor($workspace);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatarUrl' => $user->avatarMedia === null
                        ? null
                        : route('account.avatar', $user->avatarMedia->public_id),
                    'status' => $user->deletion_requested_at !== null
                        ? 'deletion_pending'
                        : ($user->is_suspended ? 'suspended' : 'active'),
                    'emailVerified' => $user->email_verified_at !== null,
                    'roles' => $user->platformRoles
                        ->map(fn (PlatformRole $role): array => [
                            'code' => $role->code,
                            'name' => $role->name,
                        ])->values()->all(),
                    'workspacesCount' => (int) $user->workspaces_count,
                    'personalWorkspace' => $workspace === null ? null : [
                        'name' => $workspace->name,
                        'membersCount' => (int) $workspace->members_count,
                        'linksCount' => (int) $workspace->links_count,
                        'bioPagesCount' => (int) $workspace->bio_pages_count,
                        'domainsCount' => (int) $workspace->custom_domains_count,
                    ],
                    'plan' => $effective === null ? null : [
                        'code' => $effective['plan']->code,
                        'name' => $effective['plan']->name,
                        'source' => $effective['source'],
                        'subscriptionStatus' => $effective['subscription']?->stripe_status,
                    ],
                    'lastSeenAt' => $user->last_seen_at?->toISOString(),
                    'createdAt' => $user->created_at?->toISOString(),
                ];
            });

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'verification' => $verification,
                'role' => $role,
                'plan' => $planCode,
            ],
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get(['code', 'name'])
                ->map(fn (Plan $plan): array => ['code' => $plan->code, 'name' => $plan->name])
                ->all(),
            'roles' => PlatformRole::query()
                ->orderBy('id')
                ->get(['code', 'name'])
                ->map(fn (PlatformRole $role): array => ['code' => $role->code, 'name' => $role->name])
                ->all(),
            'overview' => [
                'accounts' => User::query()->count(),
                'administrators' => User::query()
                    ->whereHas('platformRoles', fn (Builder $roles) => $roles->where('code', PlatformRoleCode::SuperAdmin->value))
                    ->count(),
                'suspended' => User::query()->where('is_suspended', true)->count(),
                'deletionPending' => User::query()->whereNotNull('deletion_requested_at')->count(),
                'shortLinks' => Link::query()->count(),
            ],
        ]);
    }
}
