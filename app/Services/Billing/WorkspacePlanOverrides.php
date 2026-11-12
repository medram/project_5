<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspacePlanOverride;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkspacePlanOverrides
{
    public const SOURCES = ['legacy_manual', 'manual', 'support_grant', 'promotion'];

    public function assign(
        Workspace $workspace,
        Plan $plan,
        ?User $actor,
        string $source,
        string $reason,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $expiresAt = null,
    ): WorkspacePlanOverride {
        if (! in_array($source, self::SOURCES, true)) {
            throw ValidationException::withMessages(['source' => 'Choose an approved override source.']);
        }

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Explain the override in 500 characters or fewer.']);
        }

        if ($expiresAt !== null && $expiresAt->lessThanOrEqualTo($startsAt ?? Carbon::now())) {
            throw ValidationException::withMessages(['expires_at' => 'The override expiry must be in the future.']);
        }

        return DB::transaction(function () use ($workspace, $plan, $actor, $source, $reason, $startsAt, $expiresAt): WorkspacePlanOverride {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            /** @var Workspace $lockedWorkspace */
            $now = Carbon::now();

            $existing = $lockedWorkspace->planOverrides()
                ->where('plan_id', $plan->getKey())
                ->where('source', $source)
                ->whereNull('revoked_at')
                ->where(fn ($query) => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now))
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $lockedWorkspace->planOverrides()->create([
                'plan_id' => $plan->getKey(),
                'actor_user_id' => $actor?->getKey(),
                'source' => $source,
                'reason' => $reason,
                'starts_at' => $startsAt ?? $now,
                'expires_at' => $expiresAt,
            ]);
        });
    }

    public function revoke(WorkspacePlanOverride $override, ?User $actor, string $reason): WorkspacePlanOverride
    {
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Explain the revocation in 500 characters or fewer.']);
        }

        return DB::transaction(function () use ($override, $actor, $reason): WorkspacePlanOverride {
            Workspace::query()->lockForUpdate()->findOrFail($override->workspace_id);

            $locked = WorkspacePlanOverride::query()->lockForUpdate()->findOrFail($override->getKey());
            /** @var WorkspacePlanOverride $locked */
            if ($locked->revoked_at !== null) {
                return $locked;
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor?->getKey(),
                'revocation_reason' => $reason,
            ])->save();

            return $locked;
        });
    }
}
