<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\AccountUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(private readonly AccountUsage $usage) {}

    public function updateSuspension(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'suspended' => ['required', 'boolean'],
        ]);

        $this->ensureAnotherAccount($request, $user);
        $suspended = $request->boolean('suspended');

        DB::transaction(function () use ($user, $suspended): void {
            $account = User::query()->lockForUpdate()->findOrFail($user->id);

            $account->forceFill([
                'is_suspended' => $suspended,
                'suspended_at' => $suspended ? now() : null,
                'remember_token' => $suspended ? null : $account->remember_token,
            ])->save();

            if ($suspended) {
                DB::table('sessions')->where('user_id', $account->id)->delete();
            }
        });

        return to_route('admin.index');
    }

    public function updateAdministrator(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'is_admin' => ['required', 'boolean'],
        ]);

        $this->ensureAnotherAccount($request, $user);
        $isAdministrator = $request->boolean('is_admin');

        DB::transaction(function () use ($user, $isAdministrator): void {
            User::query()
                ->lockForUpdate()
                ->findOrFail($user->id)
                ->forceFill(['is_admin' => $isAdministrator])
                ->save();
        });

        return to_route('admin.index');
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

        $this->usage->assignPlan($user, $plan);

        return to_route('admin.index');
    }

    private function ensureAnotherAccount(Request $request, User $user): void
    {
        abort_if($request->user()?->is($user), 422);
    }
}
