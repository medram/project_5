<?php

namespace App\Services\Billing;

use App\Enums\PlanMetric;
use App\Models\Workspace;
use App\Services\Notifications\WorkspaceNotificationService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnforcesPlanLimits
{
    public function __construct(private readonly WorkspaceUsage $usage) {}

    /**
     * @template T
     *
     * @param  Closure(Workspace): T  $creator
     * @return T
     *
     * @throws ValidationException
     */
    public function create(Workspace $workspace, PlanMetric $metric, Closure $creator, int $bytes = 0): mixed
    {
        if ($bytes < 0) {
            throw new \InvalidArgumentException('Quota byte reservations cannot be negative.');
        }

        return DB::transaction(function () use ($workspace, $metric, $creator, $bytes): mixed {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            /** @var Workspace $lockedWorkspace */
            $usage = $this->usage->metricFor($lockedWorkspace, $metric);
            $reservation = $metric->isByteMetric() ? $bytes : 1;

            if ($usage['limit'] !== null && $usage['limit'] < $usage['used'] + $reservation) {
                throw ValidationException::withMessages([
                    'plan' => "The current workspace plan allows {$usage['limit']} {$usage['label']}.",
                ]);
            }

            $result = $creator($lockedWorkspace);
            $updatedUsage = $this->usage->metricFor($lockedWorkspace, $metric);

            if (
                $usage['limit'] !== null
                && $usage['used'] < $usage['limit']
                && $updatedUsage['used'] >= $usage['limit']
            ) {
                app(WorkspaceNotificationService::class)->notifyManagers(
                    $lockedWorkspace,
                    'usage',
                    'Workspace usage limit reached',
                    "The workspace has reached its current plan limit for {$usage['label']}.",
                    'workspace',
                    (int) $lockedWorkspace->getKey(),
                );
            }

            return $result;
        });
    }
}
