<?php

namespace App\Models;

use App\Enums\MediaPurpose;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $uploaded_by
 * @property string $public_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $display_name
 * @property string $mime_type
 * @property int $byte_size
 * @property string $visibility
 * @property string $scan_state
 * @property MediaPurpose $purpose
 * @property CarbonInterface|null $expires_at
 */
#[Fillable([
    'workspace_id',
    'uploaded_by',
    'public_id',
    'disk',
    'path',
    'original_name',
    'display_name',
    'mime_type',
    'byte_size',
    'sha256',
    'visibility',
    'scan_state',
    'purpose',
    'expires_at',
])]
class ContentMedia extends Model
{
    use SoftDeletes;

    protected $hidden = ['disk', 'path'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'purpose' => MediaPurpose::class,
            'expires_at' => 'datetime',
        ];
    }

    public function displayName(): string
    {
        return $this->display_name ?? $this->original_name;
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
