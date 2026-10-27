<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['workspace_id', 'actor_user_id', 'route', 'method', 'status_code'])]
class ApiAuditLog extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('API audit records are immutable.'));
        static::deleting(fn () => throw new LogicException('API audit records are immutable.'));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
