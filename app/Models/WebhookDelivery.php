<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'webhook_id',
    'source_delivery_id',
    'revision',
    'event_uuid',
    'event_name',
    'body',
    'attempt_count',
    'state',
    'response_code',
    'response_excerpt',
    'failure_detail',
    'completed_at',
])]
class WebhookDelivery extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'response_code' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function sourceDelivery(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_delivery_id');
    }

    public function redeliveries(): HasMany
    {
        return $this->hasMany(self::class, 'source_delivery_id');
    }
}
