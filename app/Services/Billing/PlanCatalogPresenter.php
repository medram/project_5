<?php

namespace App\Services\Billing;

use App\Enums\PlanMetric;
use App\Models\Plan;

final class PlanCatalogPresenter
{
    public function __construct(private readonly StripeReadiness $readiness) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('monthly_price')
            ->get()
            ->map(fn (Plan $plan): array => $this->data($plan))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     monthlyPrice: int,
     *     annualPrice: int,
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

        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => is_string($metadata['description'] ?? null) ? $metadata['description'] : null,
            'monthlyPrice' => $plan->monthly_price,
            'annualPrice' => $plan->annual_price,
            'currency' => $plan->currency,
            'featured' => (bool) ($metadata['featured'] ?? false),
            'sortOrder' => (int) ($metadata['sort_order'] ?? 0),
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
                'month' => $this->readiness->priceId($plan, 'month') !== null,
                'year' => $this->readiness->priceId($plan, 'year') !== null,
            ],
        ];
    }
}
