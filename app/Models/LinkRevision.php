<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workspace_id',
    'link_id',
    'actor_user_id',
    'reviewer_user_id',
    'revision_number',
    'status',
    'snapshot',
    'review_note',
    'reviewed_at',
])]
class LinkRevision extends Model
{
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'snapshot' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
