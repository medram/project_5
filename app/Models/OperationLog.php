<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $level
 * @property string $channel
 * @property string $event
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property string|null $correlation_id
 * @property Carbon|null $created_at
 */
#[Fillable(['level', 'channel', 'event', 'message', 'context', 'correlation_id'])]
class OperationLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Operation logs are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Operation logs are append-only.'));
    }
}
