<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Accounts\RestoreAccount;
use App\Services\Accounts\ScheduleAccountDeletion;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Platform\AdminAccountMutation;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use App\Services\Platform\PlatformRoleAssignments;
use App\Services\Workspaces\CreatesPersonalWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserActionController extends Controller
{
    public function __construct(
        private readonly AdminAccountMutation $mutations,
        private readonly AdminAudit $audit,
        private readonly PlatformAuthorization $authorization,
        private readonly PlatformRoleAssignments $roleAssignments,
        private readonly WorkspaceUsage $usage,
        private readonly CreatesPersonalWorkspace $personalWorkspaces,
    ) {}

    public function updateSuspension(Request $request, User $user): RedirectResponse
    {
        $suspended = (bool) $request->validate([
            'suspended' => ['required', 'boolean'],
        ])['suspended'];

        $this->mutations->run(
            $this->actor($request),
            $user,
            PlatformPermission::AdminUsersManageStatus,
            function (User $actor, User $account) use ($suspended): void {
                if ($suspended && $this->authorization->isLastActiveSuperAdmin($account)) {
                    throw ValidationException::withMessages([
                        'account' => 'The last active super administrator cannot be suspended.',
                    ]);
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

        return $this->backToUser($user);
    }

    public function updateVerification(Request $request, User $user): RedirectResponse
    {
        $verified = (bool) $request->validate([
            'verified' => ['required', 'boolean'],
        ])['verified'];

        $this->mutations->run(
            $this->actor($request),
            $user,
            PlatformPermission::AdminUsersManageVerification,
            function (User $actor, User $account) use ($verified): void {
                $account->forceFill([
                    'email_verified_at' => $verified ? ($account->email_verified_at ?? now()) : null,
                ])->save();

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminUsersManageVerification,
                    $verified ? 'verify_email' : 'unverify_email',
                    $account,
                    null,
                    ['verified' => $verified],
                );
            },
        );

        return $this->backToUser($user);
    }

    public function resendVerification(Request $request, User $user): RedirectResponse
    {
        $this->mutations->run(
            $this->actor($request),
            $user,
            PlatformPermission::AdminUsersManageVerification,
            function (User $actor, User $account): void {
                if ($account->hasVerifiedEmail()) {
                    throw ValidationException::withMessages([
                        'verification' => 'This account is already verified.',
                    ]);
                }

                $account->sendEmailVerificationNotification();

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminUsersManageVerification,
                    'resend_verification',
                    $account,
                );
            },
        );

        return $this->backToUser($user);
    }

    public function revokeSessions(Request $request, User $user): RedirectResponse
    {
        $this->mutations->run(
            $this->actor($request),
            $user,
            PlatformPermission::AdminUsersManageStatus,
            function (User $actor, User $account): void {
                $deleted = DB::table('sessions')->where('user_id', $account->getKey())->delete();

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminUsersManageStatus,
                    'revoke_sessions',
                    $account,
                    null,
                    ['deleted' => $deleted],
                );
            },
        );

        return $this->backToUser($user);
    }

    public function revokeTokens(Request $request, User $user): RedirectResponse
    {
        $this->mutations->run(
            $this->actor($request),
            $user,
            PlatformPermission::AdminUsersManageStatus,
            function (User $actor, User $account): void {
                $deleted = $account->tokens()->delete();

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminUsersManageStatus,
                    'revoke_tokens',
                    $account,
                    null,
                    ['deleted' => $deleted],
                );
            },
        );

        return $this->backToUser($user);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(PlatformRoleCode::class)],
            'assigned' => ['required', 'boolean'],
        ]);
        $role = PlatformRoleCode::from($validated['role']);

        if ((bool) $validated['assigned']) {
            $this->roleAssignments->grant($this->actor($request), $user, $role);
        } else {
            $this->roleAssignments->revoke($this->actor($request), $user, $role);
        }

        return $this->backToUser($user);
    }

    public function scheduleDeletion(Request $request, User $user, ScheduleAccountDeletion $schedule): RedirectResponse
    {
        $this->mutations->run(
            $this->actor($request),
            $user,
            PlatformPermission::AdminUsersScheduleDeletion,
            function (User $actor, User $account) use ($schedule): void {
                $schedule->schedule($account);

                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminUsersScheduleDeletion,
                    'schedule_deletion',
                    $account,
                    null,
                    ['retention_days' => 30],
                );
            },
        );

        return $this->backToUser($user);
    }

    public function restoreDeletion(Request $request, User $user, RestoreAccount $restore): RedirectResponse
    {
        $this->mutations->run(
            $this->actor($request),
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

        return $this->backToUser($user);
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

        $this->mutations->run(
            $this->actor($request),
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

        return $this->backToUser($user);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_if($actor === null, 403);

        return $actor;
    }

    private function backToUser(User $user): RedirectResponse
    {
        $referer = request()->headers->get('referer');
        $index = route('admin.users.index');

        if (is_string($referer) && (str_starts_with($referer, $index) || str_starts_with($referer, '/admin/users'))) {
            return redirect()->to($referer);
        }

        return to_route('admin.users.show', $user);
    }
}
