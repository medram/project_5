<?php

namespace App\Models;

use App\Services\Domains\HostNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'hostname',
    'verification_token',
    'status',
    'verified_at',
    'last_checked_at',
    'failure_reason',
])]
class CustomDomain extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public static function generateVerificationToken(): string
    {
        return Str::lower(Str::random(40));
    }

    public static function normalizeHostname(string $hostname): ?string
    {
        return app(HostNormalizer::class)->normalizeHostname($hostname);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }
}
