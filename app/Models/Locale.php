<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $direction
 * @property bool $is_published
 * @property bool $is_default
 */
#[Fillable(['code', 'name', 'direction', 'is_published', 'is_default'])]
class Locale extends Model
{
    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_default' => 'boolean'];
    }

    /** @return HasMany<Translation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
