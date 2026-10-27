<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['workspace_id', 'actor_user_id', 'subject_user_id', 'action', 'metadata'])]
class WorkspaceAudit extends Model
{

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Workspace audit records are immutable.'));
        static::deleting(fn () => throw new LogicException('Workspace audit records are immutable.'));
    }
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }
}
