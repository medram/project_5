<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'uuid',
    'user_id',
    'link_id',
    'bio_page_id',
    'bio_link_id',
    'link_page_id',
    'link_page_item_id',
    'event_name',
    'occurred_at',
    'visitor_hash',
    'visitor_key_version',
    'referrer_host',
    'browser',
    'platform',
])]
class AnalyticsEvent extends Model
{
    /**
     * @var array<int, string>
     */
    protected $hidden = ['visitor_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Analytics events are immutable.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    public function bioPage(): BelongsTo
    {
        return $this->belongsTo(BioPage::class);
    }

    public function bioLink(): BelongsTo
    {
        return $this->belongsTo(BioLink::class);
    }

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    public function linkPageItem(): BelongsTo
    {
        return $this->belongsTo(LinkPageItem::class);
    }
}
