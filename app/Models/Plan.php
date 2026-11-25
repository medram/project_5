<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $monthly_price
 * @property int $annual_price
 * @property string|null $stripe_monthly_price_id
 * @property string|null $stripe_yearly_price_id
 * @property int|null $link_limit
 * @property int|null $folder_limit
 * @property int|null $qr_campaign_limit
 * @property int|null $bio_page_limit
 * @property int|null $domain_limit
 * @property int|null $pixel_limit
 * @property int|null $member_limit
 * @property int|null $webhook_limit
 * @property int|null $link_page_limit
 * @property int|null $api_key_limit
 * @property int|null $storage_byte_limit
 * @property array<string, mixed>|null $display_metadata
 * @property bool $is_active
 */
#[Fillable([
    'code',
    'name',
    'monthly_price',
    'annual_price',
    'currency',
    'link_limit',
    'folder_limit',
    'qr_campaign_limit',
    'bio_page_limit',
    'domain_limit',
    'pixel_limit',
    'webhook_limit',
    'member_limit',
    'link_page_limit',
    'api_key_limit',
    'storage_byte_limit',
    'display_metadata',
    'stripe_monthly_price_id',
    'stripe_yearly_price_id',
    'paypal_monthly_plan_id',
    'paypal_yearly_plan_id',
    'is_active',
])]
class Plan extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'annual_price' => 'integer',
            'link_limit' => 'integer',
            'folder_limit' => 'integer',
            'qr_campaign_limit' => 'integer',
            'bio_page_limit' => 'integer',
            'domain_limit' => 'integer',
            'pixel_limit' => 'integer',
            'webhook_limit' => 'integer',
            'member_limit' => 'integer',
            'link_page_limit' => 'integer',
            'api_key_limit' => 'integer',
            'storage_byte_limit' => 'integer',
            'display_metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<WorkspacePlanOverride, $this> */
    public function overrides(): HasMany
    {
        return $this->hasMany(WorkspacePlanOverride::class);
    }

    /** @return HasMany<PlanStripePrice, $this> */
    public function stripePrices(): HasMany
    {
        return $this->hasMany(PlanStripePrice::class);
    }

    /** @return HasMany<PlanPayPalPrice, $this> */
    public function paypalPrices(): HasMany
    {
        return $this->hasMany(PlanPayPalPrice::class);
    }

    public function paypalPlanIdFor(string $interval): ?string
    {
        $column = $interval === 'year' ? 'paypal_yearly_plan_id' : 'paypal_monthly_plan_id';
        $id = $this->getAttribute($column);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function stripePriceIdFor(string $interval): ?string
    {
        $column = $interval === 'year' ? 'stripe_yearly_price_id' : 'stripe_monthly_price_id';

        $price = $this->getAttribute($column);

        return is_string($price) && $price !== '' ? $price : null;
    }
}
