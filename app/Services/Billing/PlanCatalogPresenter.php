<?php

namespace App\Services\Billing;

use App\Enums\PlanMetric;
use App\Models\Plan;
use Illuminate\Support\Collection;

final class PlanCatalogPresenter
{
    public function __construct(private readonly StripeReadiness $readiness) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->ordered(
            Plan::query()->where('is_active', true)->with('stripePrices')->get(),
        )
            ->map(fn (Plan $plan): array => $this->data($plan))
            ->values()
            ->all();
    }

    /**
     * The catalog order lives in display_metadata, so it is applied in PHP
     * rather than through a database-specific JSON order clause.
     *
     * @param  Collection<int, Plan>  $plans
     * @return Collection<int, Plan>
     */
    public function ordered(Collection $plans): Collection
    {
        return $plans
            ->sort(function (Plan $left, Plan $right): int {
                $order = $this->sortOrder($left) <=> $this->sortOrder($right);

                if ($order !== 0) {
                    return $order;
                }

                $price = $left->monthly_price <=> $right->monthly_price;

                if ($price !== 0) {
                    return $price;
                }

                return (int) $left->getKey() <=> (int) $right->getKey();
            })
            ->values();
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     monthlyPrice: int|null,
     *     annualPrice: int|null,
     *     currency: string,
     *     featured: bool,
     *     sortOrder: int,
     *     limits: array<string, int|null>,
     *     checkout: array{month: bool, year: bool}
     * }
     */
    public function data(Plan $plan): array
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $plan->display_metadata ?? [];
        $monthId = $this->readiness->priceId($plan, 'month');
        $yearId = $this->readiness->priceId($plan, 'year');

        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => is_string($metadata['description'] ?? null) ? $metadata['description'] : null,
            'monthlyPrice' => $plan->code === 'free' ? 0 : ($monthId === null ? null : $plan->stripePrices->first(fn ($price) => $price->stripe_price_id === $monthId)->unit_amount),
            'annualPrice' => $plan->code === 'free' ? 0 : ($yearId === null ? null : $plan->stripePrices->first(fn ($price) => $price->stripe_price_id === $yearId)->unit_amount),
            'currency' => $plan->currency,
            'featured' => (bool) ($metadata['featured'] ?? false),
            'sortOrder' => $this->sortOrder($plan),
            'limits' => [
                PlanMetric::Links->value => $plan->link_limit,
                PlanMetric::Folders->value => $plan->folder_limit,
                PlanMetric::QrCampaigns->value => $plan->qr_campaign_limit,
                PlanMetric::BioPages->value => $plan->bio_page_limit,
                PlanMetric::Domains->value => $plan->domain_limit,
                PlanMetric::Pixels->value => $plan->pixel_limit,
                PlanMetric::Members->value => $plan->member_limit,
                PlanMetric::Webhooks->value => $plan->webhook_limit,
                PlanMetric::LinkPages->value => $plan->link_page_limit,
                PlanMetric::ApiKeys->value => $plan->api_key_limit,
                PlanMetric::StorageBytes->value => $plan->storage_byte_limit,
            ],
            'checkout' => [
                'month' => $monthId !== null,
                'year' => $yearId !== null,
            ],
        ];
    }

    private function sortOrder(Plan $plan): int
    {
        $metadata = $plan->display_metadata ?? [];

        return (int) ($metadata['sort_order'] ?? 0);
    }
}
