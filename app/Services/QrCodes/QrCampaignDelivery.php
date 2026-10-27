<?php

namespace App\Services\QrCodes;

use App\Models\Link;
use App\Models\QrCode;
use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Links\FirstPartyDestinationGuard;

class QrCampaignDelivery
{
    public function __construct(
        private readonly PublicLinkHostResolver $hosts,
        private readonly FirstPartyDestinationGuard $destinations,
    ) {}

    public function targetUrl(QrCode $qrCode): string
    {
        $hasLink = $qrCode->link_id !== null;
        $hasDestination = is_string($qrCode->destination_url) && $qrCode->destination_url !== '';

        abort_if($hasLink === $hasDestination, 404);
        abort_unless(
            ($qrCode->source === 'linked'
                && $hasLink
                && (int) $qrCode->linked_qr_link_id === (int) $qrCode->link_id)
            || ($qrCode->source === 'campaign' && $qrCode->linked_qr_link_id === null),
            404,
        );

        if ($hasLink) {
            $link = $qrCode->relationLoaded('link')
                ? $qrCode->getRelation('link')
                : Link::query()->whereKey($qrCode->link_id)->first();
            abort_unless($link instanceof Link && (int) $link->workspace_id === (int) $qrCode->workspace_id, 404);

            return $this->hosts->canonicalUrl($link);
        }

        $scheme = parse_url($qrCode->destination_url, PHP_URL_SCHEME);
        abort_unless(in_array(strtolower((string) $scheme), ['http', 'https'], true), 404);
        abort_if($this->destinations->rejects($qrCode->destination_url), 404);

        return $qrCode->destination_url;
    }

    public function isAvailable(QrCode $qrCode): bool
    {
        $at = now();

        return $qrCode->is_active
            && ($qrCode->available_from === null || $qrCode->available_from->lessThanOrEqualTo($at))
            && ($qrCode->expires_at === null || $qrCode->expires_at->greaterThan($at))
            && ($qrCode->max_scans === null || $qrCode->scan_count < $qrCode->max_scans);
    }
}
