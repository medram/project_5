<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Accounts\RestoreAccount;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use App\Services\Platform\PlatformRoleAssignments;
use App\Services\Workspaces\CreatesPersonalWorkspace;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly WorkspaceUsage $usage,
        private readonly CreatesPersonalWorkspace $personalWorkspaces,
        private readonly PlatformAuthorization $authorization,
        private readonly PlatformRoleAssignments $roleAssignments,
        private readonly AdminAudit $audit,
    ) {}

    public function updateSuspension(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'suspended' => ['required', 'boolean'],
        ]);
        $suspended = (bool) $validated['suspended'];

        $this->ensureAnotherAccount($request, $user);
        $this->mutateAsAdministrator(
            $request,
            $user,
            PlatformPermission::AdminUsersManageStatus,
            function (User $actor, User $account) use ($suspended): void {
                if ($suspended && $this->authorization->isLastActiveSuperAdmin($account)) {
                    abort(422, 'The last active super administrator cannot be suspended.');
                }

                if ($account->is_suspended === $suspended) {
                    return;
                }

                $account->forceFill([
                    'is_suspended' => $suspended,
                    'suspended_at' => $suspended ? now() : null,
                    'remember_token' => $suspended ? null : $account->remember_token,
                ])->save();

                if ($suspended) {
                    DB::table('sessions')->where('user_id', $account->id)->delete();
                }

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminUsersManageStatus,
                    'update_suspension',
                    $account,
                    null,
                    ['suspended' => $suspended],
                );
            },
        );

        return $this->redirectToIndex($request);
    }

    public function updateAdministrator(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $this->ensureAnotherAccount($request, $user);

        $actor = $request->user();
        abort_if($actor === null, 403);

        if ((bool) $validated['is_admin']) {
            $this->roleAssignments->grant($actor, $user, PlatformRoleCode::SuperAdmin);
        } else {
            $this->roleAssignments->revoke($actor, $user, PlatformRoleCode::SuperAdmin);
        }

        return $this->redirectToIndex($request);
    }

    public function restoreDeletion(Request $request, User $user, RestoreAccount $restore): RedirectResponse
    {
        $this->ensureAnotherAccount($request, $user);
        $this->mutateAsAdministrator(
            $request,
            $user,
            PlatformPermission::AdminUsersScheduleDeletion,
            function (User $actor, User $account) use ($restore): void {
                $restore->restore($account);

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminUsersScheduleDeletion,
                    'restore_deletion',
                    $account,
                );
            },
        );

        return $this->redirectToIndex($request);
    }

    public function updatePlan(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => [
                'required',
                'string',
                Rule::exists('plans', 'code')->where('is_active', true),
            ],
        ]);
        $plan = Plan::query()
            ->where('code', $validated['plan'])
            ->where('is_active', true)
            ->sole();
        $workspace = $this->personalWorkspaces->for($user);

        $this->ensureAnotherAccount($request, $user);
        $this->mutateAsAdministrator(
            $request,
            $user,
            PlatformPermission::AdminBillingManage,
            function (User $actor, User $account) use ($plan, $workspace): void {
                $this->usage->assignPlan($workspace, $plan);

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminBillingManage,
                    'assign_plan',
                    $account,
                    null,
                    ['plan' => $plan->code, 'workspace_id' => $workspace->id],
                );
            },
        );

        return $this->redirectToIndex($request);
    }

    private function redirectToIndex(Request $request): RedirectResponse
    {
        $search = $request->query('q');

        if (! is_string($search) || trim($search) === '') {
            return to_route('admin.index');
        }

        return to_route('admin.index', ['q' => mb_substr(trim($search), 0, 100)]);
    }

    private function ensureAnotherAccount(Request $request, User $user): void
    {
        abort_if($request->user()?->is($user), 422);
    }

    /**
     * Apply an account mutation inside one transaction that locks and reloads
     * both the acting administrator and the target account in ascending
     * primary-key order, then re-confirms the actor still holds the required
     * platform permission. Concurrent administrators serialize on the same
     * ordered locks, so a stale actor aborts with zero changes.
     *
     * @param  Closure(User $actor, User $target): void  $mutation
     */
    private function mutateAsAdministrator(
        Request $request,
        User $user,
        PlatformPermission $permission,
        Closure $mutation,
    ): void {
        $actorId = (int) $request->user()->getAuthIdentifier();
        $targetId = (int) $user->getKey();

        DB::transaction(function () use ($actorId, $targetId, $permission, $mutation): void {
            [$actor, $target] = $this->lockAccountsInStableOrder($actorId, $targetId);

            abort_if($actor->is($target), 422);
            abort_unless($this->authorization->allows($actor, $permission), 403);

            $mutation($actor, $target);
        });
    }

    /**
     * Lock and reload the actor and target rows in ascending primary-key order.
     *
     * @return array{0: User, 1: User}
     */
    private function lockAccountsInStableOrder(int $actorId, int $targetId): array
    {
        $accounts = User::query()
            ->whereIn('id', [$actorId, $targetId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $actor = $accounts->get($actorId);
        $target = $accounts->get($targetId);

        abort_if($actor === null || $target === null, 404);

        return [$actor, $target];
    }
}
