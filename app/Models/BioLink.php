<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BioLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $bio_page_id
 * @property string $title
 * @property string $destination_url
 * @property int $position
 * @property bool $is_active
 * @property bool $is_pinned
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 */
#[Fillable(['title', 'destination_url', 'position', 'is_active', 'is_pinned', 'starts_at', 'ends_at'])]
class BioLink extends Model
{
    /** @use HasFactory<BioLinkFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
