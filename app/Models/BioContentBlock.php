<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bio_page_id
 * @property 'text'|'image'|'social'|'embed' $type
 * @property array<string, string> $content
 * @property int|null $content_media_id
 * @property int $position
 * @property bool $is_active
 * @property bool $is_pinned
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 */
#[Fillable(['bio_page_id', 'type', 'content', 'content_media_id', 'position', 'is_active', 'is_pinned', 'starts_at', 'ends_at'])]
class BioContentBlock extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_active' => 'boolean',
            'is_pinned' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<BioPage, $this> */
    public function bioPage(): BelongsTo
    {
        return $this->belongsTo(BioPage::class);
    }

    /** @return BelongsTo<ContentMedia, $this> */
    public function contentMedia(): BelongsTo
    {
        return $this->belongsTo(ContentMedia::class);
    }
}
