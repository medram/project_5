<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * @property int $id
 * @property int|null $actor_user_id
 * @property string $permission
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $correlation_id
 * @property string|null $reason
 * @property array<string, mixed>|null $changes
 */
class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'permission',
        'action',
        'subject_type',
        'subject_id',
        'correlation_id',
        'reason',
        'changes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Admin audit records are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Admin audit records are append-only.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
