<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'workspace_id',
    'linked_qr_link_id',
    'link_id',
    'created_by',
    'code',
    'source',
    'title',
    'destination_url',
    'is_active',
    'available_from',
    'expires_at',
    'max_scans',
    'scan_count',
    'access_code',
    'access_code_version',
    'foreground_color',
    'background_color',
    'error_correction',
    'margin',
    'size',
    'logo_path',
    'logo_alt',
    'share_title',
    'share_description',
])]
class QrCode extends Model
{
    /** @var array<int, string> */
    protected $hidden = ['access_code', 'access_code_version', 'logo_path', 'linked_qr_link_id'];

    protected $appends = ['has_access_code', 'has_logo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_from' => 'datetime',
            'expires_at' => 'datetime',
            'max_scans' => 'integer',
            'scan_count' => 'integer',
            'access_code_version' => 'integer',
            'margin' => 'integer',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QrCode $qrCode): void {
            if ($qrCode->code !== null) {
                return;
            }

            do {
                $code = Str::lower(Str::random(12));
            } while (static::query()->where('code', $code)->exists());

            $qrCode->code = $code;
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }

    public function requiresAccessCode(): bool
    {
        return $this->access_code !== null;
    }

    public function getHasAccessCodeAttribute(): bool
    {
        return $this->requiresAccessCode();
    }

    public function getHasLogoAttribute(): bool
    {
        return $this->logo_path !== null;
    }
}
