<?php

namespace App\Models;

use Database\Factories\BioPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property string $handle
 * @property string $display_name
 * @property string|null $bio
 * @property bool $is_published
 * @property array<string, string>|null $appearance
 */
#[Fillable(['handle', 'display_name', 'bio', 'is_published', 'workspace_id', 'user_id', 'appearance'])]
class BioPage extends Model
{
    /** @use HasFactory<BioPageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'appearance' => 'array',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<BioLink, $this> */
    public function bioLinks(): HasMany
    {
        return $this->hasMany(BioLink::class)->orderBy('position');
    }

    /** @return HasMany<BioContentBlock, $this> */
    public function contentBlocks(): HasMany
    {
        return $this->hasMany(BioContentBlock::class)->orderBy('position');
    }

    /** @return HasMany<TrackingPixelRule, $this> */
    public function trackingPixelRules(): HasMany
    {
        return $this->hasMany(TrackingPixelRule::class, 'target_id')
            ->where('target_type', TrackingPixelRule::TARGET_BIO_PAGE);
    }

    protected static function booted(): void
    {
        static::deleting(function (BioPage $bioPage): void {
            $bioPage->trackingPixelRules()->delete();
        });
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
