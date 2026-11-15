<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $section
 * @property int $revision
 */
#[Fillable(['section', 'revision'])]
class PlatformSettingSection extends Model
{
    protected $primaryKey = 'section';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
        ];
    }
}
