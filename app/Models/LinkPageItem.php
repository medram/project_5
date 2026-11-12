<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['link_page_variant_id', 'title', 'destination_url', 'position', 'is_active'])]
class LinkPageItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_page_variant_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LinkPageItem $item): void {
            if ($item->link_page_variant_id !== null || $item->link_page_id === null) {
                return;
            }

            $variantId = LinkPageVariant::query()
                ->where('link_page_id', $item->link_page_id)
                ->where('is_control', true)
                ->value('id');
            if ($variantId !== null) {
                $item->link_page_variant_id = (int) $variantId;
            }
        });
    }

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    public function linkPageVariant(): BelongsTo
    {
        return $this->belongsTo(LinkPageVariant::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
