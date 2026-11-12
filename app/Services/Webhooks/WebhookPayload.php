<?php

namespace App\Services\Webhooks;

use App\Models\AnalyticsEvent;

class WebhookPayload
{
    /**
     * Build the exact, byte-stable JSON body for an event. The returned bytes
     * are stored once and reused verbatim on every retry.
     */
    public static function fromEvent(AnalyticsEvent $event): string
    {
        $payload = [
            'schema_version' => 1,
            'event_id' => $event->uuid,
            'event' => $event->event_name,
            'occurred_at' => $event->occurred_at->utc()->format('Y-m-d\TH:i:s\Z'),
            'workspace_id' => $event->workspace_id,
            'data' => static::dataFor($event),
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, array<string, int|string|null>>
     */
    private static function dataFor(AnalyticsEvent $event): array
    {
        return match ($event->event_name) {
            'link.clicked' => [
                'link' => static::ref($event->link_id, $event->link?->slug, 'slug'),
            ],
            'overlay.view' => [
                'link' => static::ref($event->link_id, $event->link?->slug, 'slug'),
                'overlay' => static::ref($event->link_overlay_id),
            ],
            'link_page.view' => [
                'link_page' => static::ref($event->link_page_id, $event->linkPage?->slug, 'slug'),
                ...($event->link_page_variant_id === null ? [] : [
                    'link_page_variant' => static::ref($event->link_page_variant_id, $event->linkPageVariant?->name, 'name'),
                ]),
            ],
            'link_page.clicked' => [
                'link_page' => static::ref($event->link_page_id, $event->linkPage?->slug, 'slug'),
                'link_page_item' => static::ref($event->link_page_item_id),
                ...($event->link_page_variant_id === null ? [] : [
                    'link_page_variant' => static::ref($event->link_page_variant_id, $event->linkPageVariant?->name, 'name'),
                ]),
            ],
            'link_page.lead_submitted' => [
                'link_page' => static::ref($event->link_page_id, $event->linkPage?->slug, 'slug'),
                'link_page_variant' => static::ref($event->link_page_variant_id, $event->linkPageVariant?->name, 'name'),
            ],
            'bio_page.view' => [
                'bio_page' => static::ref($event->bio_page_id, $event->bioPage?->handle, 'handle'),
            ],
            'bio_page.clicked' => [
                'bio_page' => static::ref($event->bio_page_id, $event->bioPage?->handle, 'handle'),
                'bio_link' => static::ref($event->bio_link_id),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, int|string|null>
     */
    private static function ref(?int $id, ?string $label = null, string $labelKey = 'slug'): array
    {
        $ref = ['id' => $id];

        if ($label !== null) {
            $ref[$labelKey] = $label;
        }

        return $ref;
    }
}
