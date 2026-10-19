<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'folder_id',
    'destination_url',
    'title',
    'slug',
    'is_active',
    'access_code',
    'available_from',
    'expires_at',
])]
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $hidden = ['access_code'];

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
            'available_from' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Link $link): void {
            $link->slug ??= static::generateSlug();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function isAvailableAt(CarbonInterface $at): bool
    {
        return $this->is_active
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

    private static function generateSlug(): string
    {
        do {
            $slug = strtolower(Str::random(7));
        } while (static::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
