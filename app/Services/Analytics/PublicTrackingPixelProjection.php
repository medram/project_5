<?php

namespace App\Services\Analytics;

use App\Models\BioPage;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\TrackingPixel;

class PublicTrackingPixelProjection
{
    /**
     * @return array{
     *     page_view: list<array{provider: string, identifier: string}>,
     *     button_click: list<array{provider: string, identifier: string}>,
     *     lead_submit: list<array{provider: string, identifier: string}>
     * }
     */
    public function forSurface(BioPage|LinkPage|LinkOverlay $surface, bool $consented): array
    {
        $groups = [
            'page_view' => [],
            'button_click' => [],
            'lead_submit' => [],
        ];

        if (! $consented) {
            return $groups;
        }

        $seen = [];
        $rules = $surface->trackingPixelRules()
            ->where('workspace_id', $surface->workspace_id)
            ->with('trackingPixel')
            ->orderBy('tracking_pixel_rules.id')
            ->get();

        foreach ($rules as $rule) {
            if (! array_key_exists($rule->trigger, $groups)) {
                continue;
            }

            $pixel = $rule->trackingPixel;
            if (
                ! $pixel instanceof TrackingPixel
                || (int) $pixel->workspace_id !== (int) $surface->workspace_id
                || ! $pixel->is_active
                || ! $this->hasValidIdentifier($pixel)
            ) {
                continue;
            }

            $identifier = $pixel->provider_id;
            $key = "{$pixel->provider}:{$identifier}";
            if (isset($seen[$rule->trigger][$key])) {
                continue;
            }

            $seen[$rule->trigger][$key] = true;
            $groups[$rule->trigger][] = [
                'provider' => $pixel->provider,
                'identifier' => $identifier,
            ];
        }

        return $groups;
    }

    private function hasValidIdentifier(TrackingPixel $pixel): bool
    {
        return match ($pixel->provider) {
            'meta_pixel' => preg_match('/\A\d{5,20}\z/', $pixel->provider_id) === 1,
            'google_analytics' => preg_match('/\AG-[A-Z0-9]{6,20}\z/', $pixel->provider_id) === 1,
            default => false,
        };
    }
}
