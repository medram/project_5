<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'token_id',
    'idempotency_key',
    'request_hash',
    'response_status',
    'response_body',
    'expires_at',
])]
class ApiIdempotencyKey extends Model
{
    protected function casts(): array
    {
        return [
            'response_body' => 'encrypted:array',
            'response_status' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(WorkspaceApiToken::class, 'token_id');
    }
}
