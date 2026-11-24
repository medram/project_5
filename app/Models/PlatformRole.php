<?php

namespace App\Models;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use Database\Factories\PlatformRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        static::updating(function (PlatformRole $role): void {
            if (PlatformRoleCode::tryFrom($role->getOriginal('code')) !== null || $role->isDirty('code')) {
                throw new LogicException('Built-in roles and role codes are immutable.');
            }
        });
        static::deleting(function (PlatformRole $role): void {
            if (PlatformRoleCode::tryFrom($role->code) !== null || $role->users()->exists()) {
                throw new LogicException('Built-in or assigned platform roles cannot be deleted.');
            }
        });
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return HasMany<PlatformRolePermission, $this> */
    public function permissionGrants(): HasMany
    {
        return $this->hasMany(PlatformRolePermission::class);
    }

    /** @return list<PlatformPermission> */
    public function permissions(): array
    {
        $builtIn = PlatformRoleCode::tryFrom($this->code);
        if ($builtIn !== null) {
            return $builtIn->permissions();
        }

        return $this->permissionGrants
            ->map(fn (PlatformRolePermission $grant) => PlatformPermission::tryFrom($grant->permission))
            ->filter()
            ->values()
            ->all();
    }
}
