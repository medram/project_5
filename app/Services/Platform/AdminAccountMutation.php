<?php

namespace App\Services\Platform;

use App\Enums\PlatformPermission;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class AdminAccountMutation
{
    public function __construct(private readonly PlatformAuthorization $authorization) {}

    /**
     * Lock the actor and target in ascending primary-key order, then re-confirm
     * the actor still holds the required permission and is not the target.
     *
     * @template T
     *
     * @param  Closure(User $actor, User $target): T  $mutation
     * @return T
     */
    public function run(User $actor, User $target, PlatformPermission $permission, Closure $mutation): mixed
    {
        return DB::transaction(function () use ($actor, $target, $permission, $mutation): mixed {
            $this->authorization->lockSuperAdmin();
            $accounts = User::query()
                ->whereIn('id', [$actor->getKey(), $target->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedActor = $accounts->get($actor->getKey());
            $lockedTarget = $accounts->get($target->getKey());

            abort_if($lockedActor === null || $lockedTarget === null, 404);
            abort_if($lockedActor->is($lockedTarget), 422);
            abort_unless($this->authorization->allows($lockedActor, $permission), 403);

            return $mutation($lockedActor, $lockedTarget);
        });
    }
}
