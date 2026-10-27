<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'weight', 'is_control', 'is_active'])]
class LinkPageVariant extends Model
{
    use SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_control' => 'boolean',
            'is_active' => 'boolean',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<LinkPage, $this> */
    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    /** @return HasMany<LinkPageItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LinkPageItem::class);
    }

    /** @return HasMany<LinkPageBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(LinkPageBlock::class);
    }

    /** @return HasMany<LinkPageLead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(LinkPageLead::class);
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
