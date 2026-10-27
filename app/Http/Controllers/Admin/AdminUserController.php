<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\WorkspaceUsage;
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
    ) {}

    public function updateSuspension(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'suspended' => ['required', 'boolean'],
        ]);

        $this->ensureAnotherAccount($request, $user);
        $suspended = $request->boolean('suspended');

        $this->mutateAsAdministrator($request, $user, function (User $account) use ($suspended): void {
            $account->forceFill([
                'is_suspended' => $suspended,
                'suspended_at' => $suspended ? now() : null,
                'remember_token' => $suspended ? null : $account->remember_token,
            ])->save();

            if ($suspended) {
                DB::table('sessions')->where('user_id', $account->id)->delete();
            }
        });

        return $this->redirectToIndex($request);
    }

    public function updateAdministrator(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $this->ensureAnotherAccount($request, $user);
        $isAdministrator = $request->boolean('is_admin');

        $this->mutateAsAdministrator($request, $user, function (User $account) use ($isAdministrator): void {
            $account->forceFill(['is_admin' => $isAdministrator])->save();
        });

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

        $this->ensureAnotherAccount($request, $user);

        $plan = Plan::query()
            ->where('code', $validated['plan'])
            ->where('is_active', true)
            ->sole();
        $workspace = $this->personalWorkspaces->for($user);

        $this->usage->assignPlan($workspace, $plan);

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
     * primary-key order, then re-confirms the actor is still an active
     * administrator. Concurrent administrators serialize on the same ordered
     * locks, so a mutation can never leave the platform without an active
     * administrator and a stale actor aborts with zero changes.
     *
     * @param  Closure(User $target): void  $mutation
     */
    private function mutateAsAdministrator(Request $request, User $user, Closure $mutation): void
    {
        $actorId = (int) $request->user()->getAuthIdentifier();
        $targetId = (int) $user->getKey();

        DB::transaction(function () use ($actorId, $targetId, $mutation): void {
            [$actor, $target] = $this->lockAccountsInStableOrder($actorId, $targetId);

            abort_if($actor->is($target), 422);

            abort_unless($actor->is_admin && ! $actor->is_suspended, 403);

            $mutation($target);
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
