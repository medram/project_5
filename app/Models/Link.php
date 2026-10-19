<?php

namespace App\Models;

use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['destination_url', 'title', 'slug', 'is_active'])]
#[Casts(['is_active' => 'boolean'])]
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

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

    private static function generateSlug(): string
    {
        do {
            $slug = strtolower(Str::random(7));
        } while (static::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
