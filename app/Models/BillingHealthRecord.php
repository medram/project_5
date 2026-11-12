<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $type
 * @property string|null $stripe_price
 * @property array<string, mixed>|null $details
 * @property CarbonInterface|null $resolved_at
 */
#[Fillable(['workspace_id', 'type', 'stripe_price', 'details', 'resolved_at'])]
class BillingHealthRecord extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
