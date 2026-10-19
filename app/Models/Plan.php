<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'monthly_price',
    'currency',
    'link_limit',
    'domain_limit',
    'pixel_limit',
    'webhook_limit',
    'link_page_limit',
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
            'link_limit' => 'integer',
            'domain_limit' => 'integer',
            'pixel_limit' => 'integer',
            'webhook_limit' => 'integer',
            'link_page_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }
}
