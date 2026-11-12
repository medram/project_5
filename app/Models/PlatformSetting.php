<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $section
 * @property string $key
 * @property string|null $value
 * @property bool $is_secret
 * @property int $version
 * @property int|null $updated_by
 */
#[Fillable(['section', 'key', 'value', 'is_secret', 'version', 'updated_by'])]
class PlatformSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}