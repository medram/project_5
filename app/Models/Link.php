<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** @property int|null $workspace_id */
#[Fillable([
    'user_id',
    'workspace_id',
    'folder_id',
    'custom_domain_id',
    'link_overlay_id',
    'link_page_id',
    'destination_url',
    'title',
    'slug',
    'is_active',
    'access_code',
    'access_code_version',
    'available_from',
    'expires_at',
    'delivery_mode',
    'archived_at',
    'max_clicks',
    'approval_required',
    'approval_status',
    'utm_parameters',
    'custom_parameters',
])]
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $hidden = ['access_code', 'access_code_version'];

    /**
     * @var array<int, string>
     */
    protected $appends = ['has_access_code'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'access_code_version' => 'integer',
            'available_from' => 'datetime',
            'expires_at' => 'datetime',
            'delivery_mode' => 'string',
            'archived_at' => 'datetime',
            'max_clicks' => 'integer',
            'approval_required' => 'boolean',
            'approval_status' => 'string',
            'has_pending_revision' => 'boolean',
            'utm_parameters' => 'array',
            'custom_parameters' => 'array',
            'click_count' => 'integer',
            'revision_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Link $link): void {
            $link->slug ??= static::generateSlug();
            if ($link->delivery_mode === null && $link->link_overlay_id !== null) {
                $link->delivery_mode = 'overlay';
            }
        });

        static::created(function (Link $link): void {
            if ($link->workspace_id === null) {
                return;
            }

            $link->qrCode()->create([
                'workspace_id' => $link->workspace_id,
                'source' => 'linked',
                'linked_qr_link_id' => $link->id,
                'link_id' => $link->id,
                'title' => $link->title ?? $link->slug,
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function customDomain(): BelongsTo
    {
        return $this->belongsTo(CustomDomain::class);
    }

    /** @return BelongsTo<LinkOverlay, $this> */
    public function linkOverlay(): BelongsTo
    {
        return $this->belongsTo(LinkOverlay::class);
    }

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(LinkTag::class, 'link_link_tag')->withTimestamps();
    }

    public function activeLinkOverlay(): ?LinkOverlay
    {
        $overlay = $this->linkOverlay;

        if (
            $overlay === null
            || $overlay->workspace_id !== $this->workspace_id
            || ! $overlay->isAvailableAt(now('UTC'))
        ) {
            return null;
        }

        return $overlay;
    }

    public function isAvailableAt(CarbonInterface $at): bool
    {
        return $this->is_active
            && $this->archived_at === null
            && $this->approval_status === 'approved'
            && ($this->available_from === null || $this->available_from->lessThanOrEqualTo($at))
            && ($this->expires_at === null || $this->expires_at->greaterThan($at));
    }

    public function requiresAccessCode(): bool
    {
        return $this->access_code !== null;
    }

    public function getHasAccessCodeAttribute(): bool
    {
        return $this->requiresAccessCode();
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(Click::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(LinkRoutingRule::class)->orderBy('position');
    }

    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class, 'linked_qr_link_id')
            ->where('source', 'linked');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LinkRevision::class)
            ->orderByDesc('revision_number');
    }

    private static function generateSlug(): string
    {
        $reservedSlugs = (array) config('linkpilot.reserved_slugs', []);

        do {
            $slug = strtolower(Str::random(7));
        } while (in_array($slug, $reservedSlugs, true) || static::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
