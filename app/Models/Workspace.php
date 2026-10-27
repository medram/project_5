<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'owner_id', 'personal_owner_user_id'])]
class Workspace extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')->withPivot('role')->withTimestamps();
    }

    public function personalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personal_owner_user_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(WorkspaceSubscription::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(WorkspaceAudit::class);
    }

    public function linkTags(): HasMany
    {
        return $this->hasMany(LinkTag::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function bioPages(): HasMany
    {
        return $this->hasMany(BioPage::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    /** @return HasMany<TrackingPixel, $this> */
    public function trackingPixels(): HasMany
    {
        return $this->hasMany(TrackingPixel::class);
    }

    /** @return HasMany<LinkPage, $this> */
    public function linkPages(): HasMany
    {
        return $this->hasMany(LinkPage::class);
    }

    public function linkOverlays(): HasMany
    {
        return $this->hasMany(LinkOverlay::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function analyticsEventDefinitions(): HasMany
    {
        return $this->hasMany(AnalyticsEventDefinition::class);
    }

}
