<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Subscription;

final class ScheduleAccountDeletion
{
    public function schedule(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            /** @var User $locked */
            if ($locked->deletion_requested_at !== null) {
                return;
            }

            $this->assertCanSchedule($locked);

            $locked->forceFill([
                'deletion_requested_at' => now(),
                'purge_after' => now()->addDays(30),
                'remember_token' => null,
            ])->save();

            DB::table('sessions')->where('user_id', $locked->getKey())->delete();
            $locked->tokens()->delete();
        });
    }

    public function assertCanSchedule(User $user): void
    {
        if ($user->is_admin) {
            throw ValidationException::withMessages([
                'account' => 'Remove platform administrator access before deleting this account.',
            ]);
        }

        $ownedWorkspaceIds = Workspace::query()
            ->where('owner_id', $user->getKey())
            ->pluck('id');

        $sharedWorkspace = Workspace::query()
            ->whereIn('id', $ownedWorkspaceIds)
            ->whereHas('members', fn ($members) => $members->where('users.id', '!=', $user->getKey()))
            ->first();

        if ($sharedWorkspace !== null) {
            throw ValidationException::withMessages([
                'workspaces' => "Transfer ownership of {$sharedWorkspace->name} to another member before deleting your account.",
            ]);
        }

        $hasSubscription = Subscription::query()
            ->whereIn('workspace_id', $ownedWorkspaceIds)
            ->where(function ($query): void {
                $query->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
                    ->orWhere(fn ($grace) => $grace
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '>', now()));
            })
            ->exists();

        if ($hasSubscription) {
            throw ValidationException::withMessages([
                'subscription' => 'Manage or cancel the workspace Stripe subscription before deleting this account.',
            ]);
        }
    }
}
