<?php

namespace App\Models;

use Database\Factories\FolderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'user_id', 'workspace_id'])]
class Folder extends Model
{
    /** @use HasFactory<FolderFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function clicks(): HasManyThrough
    {
        return $this->hasManyThrough(Click::class, Link::class);
    }
    public function analyticsEvents(): HasManyThrough
    {
        return $this->hasManyThrough(AnalyticsEvent::class, Link::class, 'folder_id', 'link_id')
            ->whereColumn('analytics_events.workspace_id', 'links.workspace_id');
    }
}
