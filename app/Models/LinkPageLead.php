<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $link_page_id
 * @property int $link_page_block_id
 * @property int|null $link_page_variant_id
 * @property array<string, string|null> $responses
 * @property CarbonImmutable $consented_at
 * @property CarbonImmutable $retention_expires_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['workspace_id', 'link_page_id', 'link_page_block_id', 'link_page_variant_id', 'responses', 'consented_at', 'retention_expires_at'])]
#[Hidden(['responses'])]
class LinkPageLead extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'responses' => 'encrypted:array',
            'link_page_variant_id' => 'integer',
            'consented_at' => 'immutable_datetime',
            'retention_expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<LinkPage, $this> */
    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    /** @return BelongsTo<LinkPageVariant, $this> */
    public function linkPageVariant(): BelongsTo
    {
        return $this->belongsTo(LinkPageVariant::class);
    }

    /** @return BelongsTo<LinkPageBlock, $this> */
    public function linkPageBlock(): BelongsTo
    {
        return $this->belongsTo(LinkPageBlock::class);
    }
}
