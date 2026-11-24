<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformRolePermission extends Model
{
    public $timestamps = false;

    protected $fillable = ['permission'];

    /** @return BelongsTo<PlatformRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class, 'platform_role_id');
    }
}
