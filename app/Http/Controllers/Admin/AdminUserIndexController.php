<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Models\PayPalSubscription;
use App\Models\Plan;
use App\Models\PlanStripePrice;
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
            'role' => ['nullable', 'string', Rule::in([...PlatformRole::query()->pluck('code')->all(), 'none'])],
            'plan' => ['nullable', 'string', Rule::exists('plans', 'code')],
        ]);

        $hasCustomRoles = PlatformRole::query()
            ->whereNotIn('code', [PlatformRoleCode::SuperAdmin->value, PlatformRoleCode::Support->value])
            ->exists();
        $search = trim((string) ($filters['q'] ?? ''));
        $query = User::query()
            ->with([
                'avatarMedia:id,public_id',
                'platformRoles:id,code,name',
                'personalWorkspace' => fn ($workspace) => $workspace
                    ->withCount(['links', 'bioPages', 'members', 'customDomains']),
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
            $plan = Plan::query()->where('code', $planCode)->sole();
            $allPrices = PlanStripePrice::query()->pluck('stripe_price_id')->all();
            $planPrices = PlanStripePrice::query()->where('plan_id', $plan->id)->pluck('stripe_price_id')->all();
            $activeOverride = fn (Builder $override): Builder => $override
                ->whereNull('revoked_at')
                ->where(fn (Builder $started) => $started->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn (Builder $expires) => $expires->whereNull('expires_at')->orWhere('expires_at', '>', now()));
            $validStripe = fn (Builder $subscription): Builder => $subscription
                ->where(fn (Builder $state) => $state
                    ->whereIn('stripe_status', ['active', 'trialing'])
                    ->orWhere(fn (Builder $grace) => $grace->whereNotNull('ends_at')->where('ends_at', '>', now())));
            $matchesPrices = fn (Builder $subscription, array $prices): Builder => $subscription
                ->where(fn (Builder $matches) => $matches->whereIn('stripe_price', $prices)
                    ->orWhereHas('items', fn (Builder $items) => $items->whereIn('stripe_price', $prices)));
            $validPayPal = fn (Builder $subscription): Builder => $subscription
                ->where('status', 'ACTIVE')
                ->where(fn (Builder $paid) => $paid
                    ->whereHas('price', fn (Builder $price) => $price->where('unit_amount', 0))
                    ->orWhere(fn (Builder $current) => $current->whereNotNull('last_paid_at')->where('next_billing_at', '>', now())));

            $query->whereHas('personalWorkspace', function (Builder $workspace) use ($plan, $allPrices, $planPrices, $activeOverride, $validStripe, $matchesPrices, $validPayPal): void {
                $workspace->where(fn (Builder $matches) => $matches
                    ->whereHas('planOverrides', fn (Builder $override) => $activeOverride($override)->where('plan_id', $plan->id))
                    ->orWhere(fn (Builder $withoutOverride) => $withoutOverride
                        ->whereDoesntHave('planOverrides', $activeOverride)
                        ->where(fn (Builder $providers) => $providers
                            ->whereHas('subscriptions', fn (Builder $subscription) => $matchesPrices($validStripe($subscription), $planPrices))
                            ->orWhere(fn (Builder $withoutStripe) => $withoutStripe
                                ->whereDoesntHave('subscriptions', fn (Builder $subscription) => $matchesPrices($validStripe($subscription), $allPrices))
                                ->where(fn (Builder $paypal) => $paypal
                                    ->whereHas('paypalSubscriptions', fn (Builder $subscription) => $validPayPal($subscription)
                                        ->whereHas('price', fn (Builder $price) => $price->where('plan_id', $plan->id)))
                                    ->when($plan->code === 'free', fn (Builder $fallback) => $fallback
                                        ->orWhereDoesntHave('paypalSubscriptions', $validPayPal)))))));
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
                        'subscriptionStatus' => $effective['subscription'] instanceof PayPalSubscription
                            ? $effective['subscription']->status : $effective['subscription']?->stripe_status,
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
                    ->where('is_suspended', false)
                    ->whereNull('deletion_requested_at')
                    ->whereHas('platformRoles', fn (Builder $roles) => $roles
                        ->whereIn('code', [PlatformRoleCode::SuperAdmin->value, PlatformRoleCode::Support->value])
                        ->when($hasCustomRoles, fn (Builder $roles) => $roles
                            ->orWhereHas('permissionGrants', fn (Builder $grants) => $grants
                                ->where('permission', PlatformPermission::AdminAccess->value))))
                    ->count(),
                'suspended' => User::query()->where('is_suspended', true)->count(),
                'deletionPending' => User::query()->whereNotNull('deletion_requested_at')->count(),
                'shortLinks' => Link::query()->count(),
            ],
        ]);
    }
}
