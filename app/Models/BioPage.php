<?php

namespace App\Models;

use Database\Factories\BioPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['handle', 'display_name', 'bio', 'is_published'])]
class BioPage extends Model
{
    /** @use HasFactory<BioPageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bioLinks(): HasMany
    {
        return $this->hasMany(BioLink::class)->orderBy('position');
    }
}
