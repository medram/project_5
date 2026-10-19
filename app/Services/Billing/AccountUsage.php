<?php

namespace App\Services\Billing;

use App\Models\User;
use App\Models\UserSubscription;
use InvalidArgumentException;

class AccountUsage
{
    public function __construct(private readonly CreatesFreeSubscription $freeSubscriptions) {}

    /**
     * @return array<string, array{label: string, used: int, limit: int|null, overLimit: bool}>
     */
    public function for(User $user): array
    {
        return [
            'links' => $this->metricFor($user, 'links'),
        ];
    }

    /**
     * @return array{label: string, used: int, limit: int|null, overLimit: bool}
     */
    public function metricFor(User $user, string $metric): array
    {
        $subscription = $this->subscriptionFor($user);

        return match ($metric) {
            'links' => $this->metric('Short links', $user->links()->count(), $subscription->plan->link_limit),
            default => throw new InvalidArgumentException("Unknown metered resource [{$metric}]."),
        };
    }

    public function subscriptionFor(User $user): UserSubscription
    {
        $subscription = $user->subscription()->with('plan')->first();

        if ($subscription === null) {
            $subscription = $this->freeSubscriptions->for($user);
        }

        $subscription->loadMissing('plan');

        return $subscription;
    }

    /**
     * @return array{label: string, used: int, limit: int|null, overLimit: bool}
     */
    private function metric(string $label, int $used, ?int $limit): array
    {
        return [
            'label' => $label,
            'used' => $used,
            'limit' => $limit,
            'overLimit' => $limit !== null && $used > $limit,
        ];
    }
}
