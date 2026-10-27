<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'uuid',
    'workspace_id',
    'user_id',
    'link_id',
    'bio_page_id',
    'bio_link_id',
    'link_page_id',
    'link_page_variant_id',
    'link_page_item_id',
    'link_overlay_id',
    'event_name',
    'occurred_at',
    'visitor_hash',
    'visitor_key_version',
    'referrer_host',
    'browser',
    'platform',
    'country_code',
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_term',
    'utm_content',
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
        static::creating(function (AnalyticsEvent $event): void {
            $referenceWorkspaceIds = [];
            $references = [
                ['id' => $event->link_id, 'workspace' => fn () => Link::query()->whereKey($event->link_id)->value('workspace_id')],
                ['id' => $event->bio_page_id, 'workspace' => fn () => BioPage::query()->whereKey($event->bio_page_id)->value('workspace_id')],
                ['id' => $event->bio_link_id, 'workspace' => fn () => BioLink::query()->whereKey($event->bio_link_id)->join('bio_pages', 'bio_links.bio_page_id', '=', 'bio_pages.id')->value('bio_pages.workspace_id')],
                ['id' => $event->link_page_id, 'workspace' => fn () => LinkPage::query()->whereKey($event->link_page_id)->value('workspace_id')],
                ['id' => $event->link_page_item_id, 'workspace' => fn () => LinkPageItem::query()->whereKey($event->link_page_item_id)->join('link_pages', 'link_page_items.link_page_id', '=', 'link_pages.id')->value('link_pages.workspace_id')],
                ['id' => $event->link_page_variant_id, 'workspace' => fn () => LinkPageVariant::withTrashed()->whereKey($event->link_page_variant_id)->join('link_pages', 'link_page_variants.link_page_id', '=', 'link_pages.id')->value('link_pages.workspace_id')],
                ['id' => $event->link_overlay_id, 'workspace' => fn () => LinkOverlay::query()->whereKey($event->link_overlay_id)->value('workspace_id')],
            ];
            if ($event->link_page_variant_id !== null) {
                $variantPageId = LinkPageVariant::withTrashed()
                    ->whereKey($event->link_page_variant_id)
                    ->value('link_page_id');
                if ($variantPageId === null || (int) $variantPageId !== (int) $event->link_page_id) {
                    throw new LogicException('Analytics event variant must belong to its link page.');
                }
            }


            foreach ($references as $reference) {
                if ($reference['id'] === null) {
                    continue;
                }

                $workspaceResolver = $reference['workspace'];
                $workspaceId = $workspaceResolver();
                if ($workspaceId === null) {
                    throw new LogicException('Analytics event references must exist and belong to a workspace.');
                }
                $referenceWorkspaceIds[] = (int) $workspaceId;
            }

            $referenceWorkspaceIds = array_values(array_unique($referenceWorkspaceIds));

            if (
                $referenceWorkspaceIds === []
                || count($referenceWorkspaceIds) !== 1
                || $event->workspace_id === null
                || ! Workspace::query()->whereKey($event->workspace_id)->exists()
                || (int) $event->workspace_id !== $referenceWorkspaceIds[0]
            ) {
                throw new LogicException('Analytics event references must match its workspace snapshot.');
            }
        });

        static::updating(function (): void {
            throw new LogicException('Analytics events are immutable.');
        });
    }
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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

    public function linkPageVariant(): BelongsTo
    {
        return $this->belongsTo(LinkPageVariant::class)->withTrashed();
    }

    public function linkOverlay(): BelongsTo
    {
        return $this->belongsTo(LinkOverlay::class);
    }
}
