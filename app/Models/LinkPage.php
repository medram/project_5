<?php

namespace App\Models;

use Database\Factories\LinkPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $workspace_id
 */
#[Fillable(['name', 'slug', 'title', 'description', 'template', 'is_published', 'workspace_id', 'user_id'])]
class LinkPage extends Model
{
    /** @use HasFactory<LinkPageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return HasMany<LinkPageItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LinkPageItem::class)->orderBy('position');
    }

    /** @return HasMany<LinkPageBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(LinkPageBlock::class)->orderBy('position');
    }

    /** @return HasMany<LinkPageVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(LinkPageVariant::class);
    }

    /** @return HasOne<LinkPageVariant, $this> */
    public function controlVariant(): HasOne
    {
        return $this->hasOne(LinkPageVariant::class)->where('is_control', true);
    }

    /** @return HasMany<TrackingPixelRule, $this> */
    public function trackingPixelRules(): HasMany
    {
        return $this->hasMany(TrackingPixelRule::class, 'target_id')
            ->where('target_type', TrackingPixelRule::TARGET_LINK_PAGE);
    }

    protected static function booted(): void
    {
        static::deleting(function (LinkPage $linkPage): void {
            $linkPage->trackingPixelRules()->delete();
        });
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}
