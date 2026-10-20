<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'url', 'events', 'revision', 'is_active'])]
class Webhook extends Model
{
    /**
     * @var array<int, string>
     */
    protected $hidden = ['secret'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'revision' => 'integer',
            'secret_rotated_at' => 'datetime',
        ];
    }

    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function supportedEvents(): array
    {
        return [
            'link.clicked',
            'overlay.view',
            'link_page.view',
            'link_page.clicked',
            'bio_page.view',
            'bio_page.clicked',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @return string The HMAC signature header value for an exact body.
     */
    public function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, (string) $this->secret);
    }

    /**
     * Rotate the signing secret and cancel any in-flight deliveries.
     */
    public function rotateSecret(): string
    {
        $secret = static::generateSecret();

        $this->forceFill([
            'secret' => $secret,
            'revision' => $this->revision + 1,
            'secret_rotated_at' => now(),
        ])->save();

        return $secret;
    }
}
