<?php

namespace App\Models;

use App\Services\Workspaces\CreatesPersonalWorkspace;
use Database\Factories\UserFactory;
use DateTimeInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property bool $is_admin
 * @property bool $is_suspended
 * @property int|null $current_workspace_id
 * @property int|null $avatar_media_id
 * @property string|null $locale
 * @property string|null $country_code
 * @property string|null $timezone
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $purge_after
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'locale', 'country_code', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_suspended' => 'boolean',
            'suspended_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            app(CreatesPersonalWorkspace::class)->for($user);
        });
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function personalWorkspace(): HasOne
    {
        return $this->hasOne(Workspace::class, 'personal_owner_user_id');
    }

    public function currentWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'current_workspace_id');
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** @return BelongsTo<ContentMedia, $this> */
    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(ContentMedia::class, 'avatar_media_id');
    }

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function belongsToWorkspace(Workspace $workspace): bool
    {
        return $this->workspaces()->whereKey($workspace->getKey())->exists();
    }

    public function sharesWorkspaceWith(User $other): bool
    {
        if ($this->is($other)) {
            return true;
        }

        return $this->workspaces()
            ->whereHas('members', fn ($members) => $members->whereKey($other->getKey()))
            ->exists();
    }

    public function createWorkspaceApiToken(
        Workspace $workspace,
        string $name,
        array $abilities,
        ?DateTimeInterface $expiresAt = null,
    ): NewAccessToken {
        $plainTextToken = $this->generateTokenString();
        $accessToken = $this->tokens()->create([
            'workspace_id' => $workspace->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return new NewAccessToken($accessToken, $accessToken->getKey().'|'.$plainTextToken);
    }
}
