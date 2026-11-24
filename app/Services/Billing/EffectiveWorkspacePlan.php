<?php

namespace App\Services\Billing;

use App\Models\BillingHealthRecord;
use App\Models\Plan;
use App\Models\PlanStripePrice;
use App\Models\Workspace;
use App\Models\WorkspacePlanOverride;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Subscription;

/**
 * Resolves one workspace entitlement from, in order: a current plan override,
 * a valid provider subscription mapped by price, then Free.
 */
final class EffectiveWorkspacePlan
{
    /**
     * @return array{plan: Plan, source: string, override: WorkspacePlanOverride|null, subscription: Subscription|null}
     */
    public function for(Workspace $workspace): array
    {
        $override = $this->currentOverride($workspace);

        if ($override !== null) {
            return [
                'plan' => $override->plan,
                'source' => $override->source,
                'override' => $override,
                'subscription' => null,
            ];
        }

        $subscription = $this->validSubscription($workspace);

        if ($subscription !== null) {
            $plan = $this->planForSubscription($subscription);

            if ($plan !== null) {
                BillingHealthRecord::query()
                    ->where('workspace_id', $workspace->getKey())
                    ->where('type', 'unmapped_stripe_price')
                    ->whereNull('resolved_at')
                    ->update(['resolved_at' => now()]);

                return [
                    'plan' => $plan,
                    'source' => 'stripe',
                    'override' => null,
                    'subscription' => $subscription,
                ];
            }

            $this->recordUnmappedPrice($workspace, $subscription);
        }

        return [
            'plan' => $this->freePlan(),
            'source' => 'free',
            'override' => null,
            'subscription' => $subscription,
        ];
    }

    public function currentOverride(Workspace $workspace): ?WorkspacePlanOverride
    {
        $now = now();

        return $workspace->planOverrides()
            ->with('plan')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', $now))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    private function validSubscription(Workspace $workspace): ?Subscription
    {
        $now = Carbon::now();

        return $workspace->subscriptions()
            ->where(function ($query) use ($now): void {
                $query->whereIn('stripe_status', ['active', 'trialing'])
                    ->orWhere(fn ($grace) => $grace
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '>', $now));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    private function planForSubscription(Subscription $subscription): ?Plan
    {
        $prices = collect([$subscription->stripe_price])
            ->merge($subscription->items->pluck('stripe_price'))
            ->filter(fn (mixed $price): bool => is_string($price) && $price !== '')
            ->unique()
            ->values();

        if ($prices->isEmpty()) {
            return null;
        }

        $mappings = PlanStripePrice::query()->with('plan')->whereIn('stripe_price_id', $prices)->get()->keyBy('stripe_price_id');

        foreach ($prices as $price) {
            if (isset($mappings[$price])) {
                return $mappings[$price]->plan;
            }
        }

        return null;
    }

    private function recordUnmappedPrice(Workspace $workspace, Subscription $subscription): void
    {
        $prices = collect([$subscription->stripe_price])
            ->merge($subscription->items->pluck('stripe_price'))
            ->filter(fn (mixed $price): bool => is_string($price) && $price !== '')
            ->unique()
            ->values();

        BillingHealthRecord::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->getKey(),
                'type' => 'unmapped_stripe_price',
                'stripe_price' => mb_substr($prices->implode(','), 0, 191),
            ],
            [
                'details' => [
                    'subscription_id' => $subscription->getKey(),
                    'subscription_status' => $subscription->stripe_status,
                    'prices' => $prices->all(),
                ],
                'resolved_at' => null,
            ],
        );
    }

    private function freePlan(): Plan
    {
        return Plan::query()->where('code', 'free')->firstOrFail();
    }
}
