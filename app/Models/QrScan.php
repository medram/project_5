<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'qr_code_id',
    'scanned_at',
    'country_code',
    'browser',
    'platform',
    'referrer_host',
])]
class QrScan extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }
}
