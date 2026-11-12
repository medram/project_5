<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property bool $email_verified
 * @property CarbonInterface $linked_at
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'email', 'email_verified', 'linked_at'])]
class SocialAccount extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'linked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
