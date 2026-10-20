<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'webhook_id',
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
}
