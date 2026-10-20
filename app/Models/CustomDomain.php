<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
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
        $hostname = strtolower(rtrim(trim($hostname), '.'));

        if (
            $hostname === ''
            || strlen($hostname) > 253
            || str_contains($hostname, '..')
            || preg_match('/\A[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\z/', $hostname) !== 1
        ) {
            return null;
        }

        return $hostname;
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
