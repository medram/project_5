<?php

namespace App\Services\Billing;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnforcesPlanLimits
{
    public function __construct(private readonly AccountUsage $usage) {}

    /**
     * @template T
     *
     * @param  Closure(User): T  $creator
     * @return T
     *
     * @throws ValidationException
     */
    public function create(User $user, string $metric, Closure $creator): mixed
    {
        return DB::transaction(function () use ($user, $metric, $creator): mixed {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $usage = $this->usage->metricFor($lockedUser, $metric);

            if ($usage['limit'] !== null && $usage['used'] >= $usage['limit']) {
                throw ValidationException::withMessages([
                    'plan' => "Your current plan allows {$usage['limit']} {$usage['label']}.",
                ]);
            }

            return $creator($lockedUser);
        });
    }
}
