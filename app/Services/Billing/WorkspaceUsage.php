<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Services\Notifications\WorkspaceNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkspaceUsage
{
    public function planFor(Workspace $workspace): Plan
    {
        $subscription = $workspace->subscription()->with('plan')->first();

        return $subscription?->plan ?? Plan::query()->where('code', 'free')->sole();
    }

    public function subscriptionFor(Workspace $workspace): WorkspaceSubscription
    {
        return $workspace->subscription()->firstOrCreate([], [
            'plan_id' => Plan::query()->where('code', 'free')->sole()->id,
            'status' => 'active',
            'source' => 'manual',
        ]);
    }

    /**
     * @return array<string, array{label: string, used: int, limit: int|null, overLimit: bool}>
     */
    public function for(Workspace $workspace): array
    {
        $plan = $this->planFor($workspace);

        return [
            'links' => $this->metric('Short links', $workspace->links()->count(), $plan->link_limit),
            'domains' => $this->metric('Custom domains', $workspace->customDomains()->count(), $plan->domain_limit),
            'pixels' => $this->metric('Tracking pixels', $workspace->trackingPixels()->count(), $plan->pixel_limit),
            'linkPages' => $this->metric('Link pages', $workspace->linkPages()->count(), $plan->link_page_limit),
            'webhooks' => $this->metric('Webhooks', $workspace->webhooks()->count(), $plan->webhook_limit),
        ];
    }

    /**
     * @return array{label: string, used: int, limit: int|null, overLimit: bool}
     */
    public function metricFor(Workspace $workspace, string $metric): array
    {
        $plan = $this->planFor($workspace);

        return match ($metric) {
            'links' => $this->metric('Short links', $workspace->links()->count(), $plan->link_limit),
            'domains' => $this->metric('Custom domains', $workspace->customDomains()->count(), $plan->domain_limit),
            'pixels' => $this->metric('Tracking pixels', $workspace->trackingPixels()->count(), $plan->pixel_limit),
            'link_pages' => $this->metric('Link pages', $workspace->linkPages()->count(), $plan->link_page_limit),
            'qr_campaigns' => $this->metric(
                'Direct QR campaigns',
                $workspace->qrCodes()->where('source', 'campaign')->whereNotNull('destination_url')->count(),
                $plan->qr_campaign_limit,
            ),
            'webhooks' => $this->metric('Webhooks', $workspace->webhooks()->count(), $plan->webhook_limit),
            default => throw new \InvalidArgumentException("Unknown metered resource [{$metric}]."),
        };
    }

    public function assignPlan(Workspace $workspace, Plan $plan): WorkspaceSubscription
    {
        return DB::transaction(function () use ($workspace, $plan): WorkspaceSubscription {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            $subscription = $this->subscriptionFor($lockedWorkspace);
            $previousPlanId = $subscription->plan_id;
            $subscription->update([
                'plan_id' => $plan->getKey(),
                'status' => 'active',
                'source' => 'manual',
            ]);

            if ((int) $previousPlanId !== (int) $plan->getKey()) {
                app(WorkspaceNotificationService::class)->notifyManagers(
                    $lockedWorkspace,
                    'billing',
                    'Workspace plan updated',
                    "The workspace plan is now {$plan->name}.",
                    'workspace',
                    (int) $lockedWorkspace->getKey(),
                );
            }

            return WorkspaceSubscription::query()
                ->with('plan')
                ->findOrFail($subscription->getKey());
        });
    }

    /** @return array{plan: string, limit: int|null, members: int, pending: int, available: int|null, overLimit: bool} */
    public function memberCapacityFor(Workspace $workspace): array
    {
        $members = $workspace->members()->count();
        $pending = $workspace->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();
        $plan = $this->planFor($workspace);
        $limit = $plan->member_limit;

        return [
            'plan' => $plan->name,
            'limit' => $limit,
            'members' => $members,
            'pending' => $pending,
            'available' => $limit === null ? null : max(0, $limit - $members - $pending),
            'overLimit' => $limit !== null && $members + $pending > $limit,
        ];
    }

    public function assertCanReserveInvitation(Workspace $workspace): void
    {
        $capacity = $this->memberCapacityFor($workspace);

        if ($capacity['limit'] !== null && $capacity['limit'] <= $capacity['members'] + $capacity['pending']) {
            throw ValidationException::withMessages([
                'plan' => "The {$capacity['plan']} plan allows up to {$capacity['limit']} workspace members, including pending invitations.",
            ]);
        }
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
