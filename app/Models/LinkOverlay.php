<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LinkOverlayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $workspace_id
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $expires_at
 */
#[Fillable(['name', 'heading', 'body', 'cta_label', 'is_active', 'starts_at', 'expires_at', 'display_delay_seconds', 'workspace_id', 'user_id'])]
class LinkOverlay extends Model
{
    /** @use HasFactory<LinkOverlayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'display_delay_seconds' => 'integer',
        ];
    }

    public function isAvailableAt(CarbonInterface $at): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($at))
            && ($this->expires_at === null || $this->expires_at->greaterThan($at));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    /** @return HasMany<TrackingPixelRule, $this> */
    public function trackingPixelRules(): HasMany
    {
        return $this->hasMany(TrackingPixelRule::class, 'target_id')
            ->where('target_type', TrackingPixelRule::TARGET_LINK_OVERLAY);
    }

    protected static function booted(): void
    {
        static::deleting(function (LinkOverlay $overlay): void {
            $overlay->trackingPixelRules()->delete();
        });
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
