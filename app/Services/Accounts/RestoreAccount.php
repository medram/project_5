<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RestoreAccount
{
    public function restore(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->getKey());
            /** @var User $locked */
            if ($locked->deletion_requested_at !== null) {
                $locked->forceFill([
                    'deletion_requested_at' => null,
                    'purge_after' => null,
                ])->save();
            }

            return $locked;
        });
    }
}
