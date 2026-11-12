<?php

namespace App\Models;

use App\Enums\PlatformRoleCode;
use Database\Factories\PlatformRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property-read int $users_count
 */
class PlatformRole extends Model
{
    /** @use HasFactory<PlatformRoleFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name'];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Platform roles are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Platform roles are immutable.'));
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function roleCode(): PlatformRoleCode
    {
        return PlatformRoleCode::from($this->code);
    }
}
