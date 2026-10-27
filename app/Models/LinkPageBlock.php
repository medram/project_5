<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $link_page_id
 * @property 'text'|'image'|'form'|'file' $type
 * @property array<string, mixed> $content
 * @property int|null $link_page_variant_id
 * @property int|null $content_media_id
 * @property int $position
 * @property bool $is_active
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 */
#[Fillable(['link_page_id', 'link_page_variant_id', 'type', 'content', 'content_media_id', 'position', 'is_active', 'starts_at', 'ends_at'])]
class LinkPageBlock extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'link_page_variant_id' => 'integer',
            'content' => 'array',
            'position' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
    protected static function booted(): void
    {
        static::creating(function (LinkPageBlock $block): void {
            if ($block->link_page_variant_id !== null || $block->link_page_id === null) {
                return;
            }

            $variantId = LinkPageVariant::query()
                ->where('link_page_id', $block->link_page_id)
                ->where('is_control', true)
                ->value('id');
            if ($variantId !== null) {
                $block->link_page_variant_id = (int) $variantId;
            }
        });
    }


    /** @return BelongsTo<LinkPage, $this> */
    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    /** @return BelongsTo<LinkPageVariant, $this> */
    public function linkPageVariant(): BelongsTo
    {
        return $this->belongsTo(LinkPageVariant::class);
    }

    /** @return BelongsTo<ContentMedia, $this> */
    public function contentMedia(): BelongsTo
    {
        return $this->belongsTo(ContentMedia::class);
    }
}
