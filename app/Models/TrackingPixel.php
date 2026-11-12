<?php

namespace App\Models;

use Database\Factories\TrackingPixelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'provider', 'provider_id', 'is_active', 'workspace_id', 'user_id'])]
class TrackingPixel extends Model
{
    /** @use HasFactory<TrackingPixelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return HasMany<TrackingPixelRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(TrackingPixelRule::class);
    }
}
