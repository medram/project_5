<?php

namespace App\Services\Billing;

use App\Enums\PlanMetric;
use App\Models\ContentMedia;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceApiToken;
use App\Models\WorkspacePlanOverride;
use App\Models\WorkspaceSubscription;
use App\Services\Notifications\WorkspaceNotificationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Subscription;

class WorkspaceUsage
{
    public function __construct(
        private readonly EffectiveWorkspacePlan $effectivePlan,
        private readonly WorkspacePlanOverrides $overrides,
    ) {}

    public function planFor(Workspace $workspace): Plan
    {
        return $this->effectivePlan->for($workspace)['plan'];
    }

    /**
     * @return array{plan: Plan, source: string, override: WorkspacePlanOverride|null, subscription: Subscription|null}
     */
    public function effectivePlanFor(Workspace $workspace): array
    {
        return $this->effectivePlan->for($workspace);
    }

    /**
     * The legacy local subscription row backs the current billing screen until
     * provider-owned billing replaces it; it never decides the effective plan.
     */
    public function subscriptionFor(Workspace $workspace): WorkspaceSubscription
    {
        return $this->legacySubscription($workspace);
    }

    /**
     * @return array<string, array{label: string, used: int, limit: int|null, overLimit: bool}>
     */
    public function for(Workspace $workspace): array
    {
        $links = $this->metricFor($workspace, PlanMetric::Links);
        $domains = $this->metricFor($workspace, PlanMetric::Domains);
        $pixels = $this->metricFor($workspace, PlanMetric::Pixels);
        $linkPages = $this->metricFor($workspace, PlanMetric::LinkPages);
        $webhooks = $this->metricFor($workspace, PlanMetric::Webhooks);

        return [
            'links' => $links,
            'folders' => $this->metricFor($workspace, PlanMetric::Folders),
            'qrCampaigns' => $this->metricFor($workspace, PlanMetric::QrCampaigns),
            'bioPages' => $this->metricFor($workspace, PlanMetric::BioPages),
            'domains' => $domains,
            'pixels' => $pixels,
            'members' => $this->metricFor($workspace, PlanMetric::Members),
            'webhooks' => $webhooks,
            'linkPages' => $linkPages,
            'apiKeys' => $this->metricFor($workspace, PlanMetric::ApiKeys),
            'storage' => $this->metricFor($workspace, PlanMetric::StorageBytes),
        ];
    }

    /**
     * @return array{label: string, used: int, limit: int|null, overLimit: bool}
     */
    public function metricFor(Workspace $workspace, PlanMetric $metric): array
    {
        $plan = $this->planFor($workspace);
        $limit = $plan->getAttribute($metric->planColumn());

        return $this->metric($metric->label(), $this->usedFor($workspace, $metric), $limit);
    }

    public function assignPlan(Workspace $workspace, Plan $plan): WorkspaceSubscription
    {
        return DB::transaction(function () use ($workspace, $plan): WorkspaceSubscription {
            $lockedWorkspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->getKey());
            /** @var Workspace $lockedWorkspace */
            $previousPlanId = (int) $this->planFor($lockedWorkspace)->getKey();
            $subscription = $this->legacySubscription($lockedWorkspace);
            $subscription->update([
                'plan_id' => $plan->getKey(),
                'status' => 'active',
                'source' => 'manual',
            ]);

            $this->overrides->assign(
                $lockedWorkspace,
                $plan,
                null,
                'legacy_manual',
                'Legacy manual plan assignment.',
            );

            if ($previousPlanId !== (int) $plan->getKey()) {
                app(WorkspaceNotificationService::class)->notifyManagers(
                    $lockedWorkspace,
                    'billing',
                    'Workspace plan updated',
                    "The workspace plan is now {$plan->name}.",
                    'workspace',
                    (int) $lockedWorkspace->getKey(),
                );
            }

            /** @var WorkspaceSubscription $updated */
            $updated = WorkspaceSubscription::query()
                ->with('plan')
                ->findOrFail($subscription->getKey());

            return $updated;
        });
    }

    /**
     * @param  CarbonInterface|null  $expiresAt  Reserved for the admin override workflow.
     */
    public function assignOverride(
        Workspace $workspace,
        Plan $plan,
        ?User $actor,
        string $source,
        string $reason,
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $expiresAt = null,
    ): WorkspacePlanOverride {
        return $this->overrides->assign($workspace, $plan, $actor, $source, $reason, $startsAt, $expiresAt);
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

    private function legacySubscription(Workspace $workspace): WorkspaceSubscription
    {
        return $workspace->legacySubscription()->firstOrCreate([], [
            'plan_id' => Plan::query()->where('code', 'free')->sole()->id,
            'status' => 'active',
            'source' => 'manual',
        ]);
    }

    private function usedFor(Workspace $workspace, PlanMetric $metric): int
    {
        return match ($metric) {
            PlanMetric::Links => $workspace->links()->count(),
            PlanMetric::Folders => $workspace->folders()->count(),
            PlanMetric::QrCampaigns => $workspace->qrCodes()
                ->where('source', 'campaign')
                ->whereNotNull('destination_url')
                ->count(),
            PlanMetric::BioPages => $workspace->bioPages()->count(),
            PlanMetric::Domains => $workspace->customDomains()->count(),
            PlanMetric::Pixels => $workspace->trackingPixels()->count(),
            PlanMetric::Members => $workspace->members()->count(),
            PlanMetric::Webhooks => $workspace->webhooks()->count(),
            PlanMetric::LinkPages => $workspace->linkPages()->count(),
            PlanMetric::ApiKeys => WorkspaceApiToken::query()
                ->where('workspace_id', $workspace->getKey())
                ->where(fn ($query) => $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now()))
                ->count(),
            PlanMetric::StorageBytes => (int) ContentMedia::withTrashed()
                ->where('workspace_id', $workspace->getKey())
                ->sum('byte_size'),
        };
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
