<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property string $state
 * @property string|null $placement
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $og_media_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonInterface|null $published_at
 */
#[Fillable([
    'slug',
    'title',
    'body',
    'state',
    'placement',
    'seo_title',
    'seo_description',
    'og_media_id',
    'created_by',
    'updated_by',
    'published_at',
])]
class CustomPage extends Model
{
    public const RESERVED_SLUGS = [
        'admin',
        'api',
        'account-settings',
        'billing',
        'confirm-password',
        'content',
        'custom-domains',
        'dashboard',
        'folders',
        'forgot-password',
        'invitations',
        'links',
        'login',
        'logout',
        'pages',
        'password',
        'pricing',
        'qr-codes',
        'register',
        'reset-password',
        'settings',
        'storage',
        'stripe',
        'tracking-consent',
        'two-factor-challenge',
        'up',
        'user',
        'verify-email',
        'webhooks',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @template TModel of CustomPage
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('state', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_SLUGS, true);
    }

    /** @return BelongsTo<ContentMedia, $this> */
    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(ContentMedia::class, 'og_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isPublished(): bool
    {
        return $this->state === 'published' && $this->published_at !== null;
    }
}
