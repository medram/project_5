<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRoleCode;
use App\Http\Controllers\Controller;
use App\Models\PayPalSubscription;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AdminUserShowController extends Controller
{
    public function __construct(private readonly WorkspaceUsage $usage) {}

    public function __invoke(Request $request, User $user): Response
    {
        $account = $user;
        $account->load(['avatarMedia:id,public_id', 'platformRoles:id,code,name']);

        $workspaces = $account->workspaces()
            ->withPivot('role')
            ->withCount(['members', 'links', 'bioPages', 'customDomains'])
            ->orderBy('id')
            ->get()
            ->map(function (Workspace $workspace) use ($account): array {
                $effective = $this->usage->effectivePlanFor($workspace);

                return [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'role' => (string) $workspace->pivot->getAttribute('role'),
                    'isOwner' => (int) $workspace->owner_id === (int) $account->id
                        || (int) $workspace->personal_owner_user_id === (int) $account->id,
                    'isPersonal' => $workspace->personal_owner_user_id !== null,
                    'membersCount' => (int) $workspace->members_count,
                    'linksCount' => (int) $workspace->links_count,
                    'bioPagesCount' => (int) $workspace->bio_pages_count,
                    'domainsCount' => (int) $workspace->custom_domains_count,
                    'plan' => [
                        'code' => $effective['plan']->code,
                        'name' => $effective['plan']->name,
                        'source' => $effective['source'],
                        'subscriptionStatus' => $effective['subscription'] instanceof PayPalSubscription
                            ? $effective['subscription']->status : $effective['subscription']?->stripe_status,
                    ],
                ];
            })
            ->values()
            ->all();

        $sessionQuery = DB::table('sessions')->where('user_id', $account->getKey());
        $latestActivity = (clone $sessionQuery)->max('last_activity');
        $tokens = $account->tokens()
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at'])
            ->map(fn ($token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'lastUsedAt' => $token->last_used_at?->toISOString(),
                'expiresAt' => $token->expires_at?->toISOString(),
                'createdAt' => $token->created_at?->toISOString(),
            ])
            ->all();

        $roleOptions = PlatformRole::query()->orderBy('id')->get(['id', 'code', 'name']);
        $roleOptions->filter(fn (PlatformRole $role) => PlatformRoleCode::tryFrom($role->code) === null)
            ->load('permissionGrants');
        $actorRoles = $request->user()->platformRoles()->pluck('code')->all();

        return Inertia::render('admin/users/show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'avatarUrl' => $account->avatarMedia === null
                    ? null
                    : route('account.avatar', $account->avatarMedia->public_id),
                'status' => $account->deletion_requested_at !== null
                    ? 'deletion_pending'
                    : ($account->is_suspended ? 'suspended' : 'active'),
                'emailVerified' => $account->email_verified_at !== null,
                'emailVerifiedAt' => $account->email_verified_at?->toISOString(),
                'roles' => $account->platformRoles
                    ->map(fn (PlatformRole $role): array => [
                        'code' => $role->code,
                        'name' => $role->name,
                    ])->values()->all(),
                'createdAt' => $account->created_at?->toISOString(),
                'updatedAt' => $account->updated_at?->toISOString(),
                'lastSeenAt' => $account->last_seen_at?->toISOString(),
                'suspendedAt' => $account->suspended_at?->toISOString(),
                'deletionRequestedAt' => $account->deletion_requested_at?->toISOString(),
                'purgeAfter' => $account->purge_after?->toISOString(),
                'isSelf' => $request->user()?->is($account) ?? false,
            ],
            'security' => [
                'twoFactorEnabled' => $account->two_factor_confirmed_at !== null,
                'passkeyCount' => Features::canManagePasskeys() && Schema::hasTable('passkeys')
                    ? $account->passkeys()->count()
                    : 0,
                'sessionCount' => (clone $sessionQuery)->count(),
                'latestSessionAt' => $latestActivity === null
                    ? null
                    : now()->setTimestamp((int) $latestActivity)->toISOString(),
                'tokenCount' => $account->tokens()->count(),
                'tokens' => $tokens,
            ],
            'workspaces' => $workspaces,
            'roles' => $roleOptions->map(fn (PlatformRole $role): array => [
                    'code' => $role->code,
                    'name' => $role->name,
                    'isBuiltIn' => PlatformRoleCode::tryFrom($role->code) !== null,
                    'isAssignedToActor' => in_array($role->code, $actorRoles, true),
                    'permissions' => array_map(
                        fn ($permission): array => [
                            'value' => $permission->value,
                            'label' => $permission->label(),
                        ],
                        $role->permissions(),
                    ),
                ])->values()->all(),
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get(['code', 'name'])
                ->map(fn (Plan $plan): array => ['code' => $plan->code, 'name' => $plan->name])
                ->all(),
        ]);
    }
}
