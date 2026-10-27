<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsEventDefinition;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\LinkPageVariant;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AnalyticsTracker
{
    private const BUILT_IN_EVENT_NAMES = [
        'link.clicked',
        'overlay.view',
        'link_page.view',
        'link_page.clicked',
        'bio_page.view',
        'link_page.lead_submitted',
        'bio_page.clicked',
    ];

    public function __construct(
        private readonly TrackingConsent $consent,
        private readonly GeoIpCountryResolver $geoIp,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    public static function isValidCustomEventName(string $eventName): bool
    {
        return strlen($eventName) <= 64
            && preg_match('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $eventName) === 1
            && ! in_array($eventName, self::BUILT_IN_EVENT_NAMES, true);
    }

    public function canTrack(Request $request): bool
    {
        return $this->isEnabled() && $this->consent->allows($request);
    }

    /**
     * @param  User|null  $user  creator attribution; null once the creator
     *                           account has been deleted and the resource is
     *                           retained by its workspace
     * @param  array<string, mixed>  $references
     */
    public function record(
        Request $request,
        string $eventName,
        ?User $user,
        array $references = [],
    ): ?AnalyticsEvent {
        $isBuiltIn = in_array($eventName, self::BUILT_IN_EVENT_NAMES, true);

        if (! $isBuiltIn && ! self::isValidCustomEventName($eventName)) {
            throw new InvalidArgumentException("Unsupported analytics event [{$eventName}].");
        }

        if (! $this->canTrack($request)) {
            return null;
        }

        $referenceAttributes = $this->referenceAttributes($references);
        $workspaceId = $referenceAttributes['workspace_id'];
        unset($referenceAttributes['workspace_id']);

        if (
            ! $isBuiltIn
            && ! AnalyticsEventDefinition::query()
                ->where('workspace_id', $workspaceId)
                ->where('event_name', $eventName)
                ->where('is_active', true)
                ->exists()
        ) {
            throw new InvalidArgumentException("Unsupported analytics event [{$eventName}].");
        }
        $workspace = Workspace::query()->findOrFail($workspaceId);
        $event = $workspace->analyticsEvents()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'event_name' => $eventName,
            'occurred_at' => now('UTC'),
            'visitor_hash' => $this->visitorHash($request),
            'visitor_key_version' => $this->visitorKeyVersion(),
            'referrer_host' => $this->referrerHost($request),
            'browser' => $this->browser($request),
            'platform' => $this->platform($request),
            'country_code' => $this->geoIp->countryCode((string) $request->ip()),
            ...$this->utmAttributes($request),
            ...$referenceAttributes,
        ]);

        $this->dispatchToWebhooks($event);

        return $event;
    }

    /**
     * Record a declared server-side event without inventing visitor or request dimensions.
     *
     * @param  array<string, mixed>  $references
     */
    public function recordDeclaredApiEvent(
        Workspace $workspace,
        string $eventName,
        User $actor,
        array $references,
    ): AnalyticsEvent {
        if (! self::isValidCustomEventName($eventName)) {
            throw new InvalidArgumentException("Unsupported analytics event [{$eventName}].");
        }

        $referenceAttributes = $this->referenceAttributes($references);
        $referenceWorkspaceId = (int) $referenceAttributes['workspace_id'];
        unset($referenceAttributes['workspace_id']);

        $referenceCount = count(array_filter([
            $referenceAttributes['link_id'] ?? null,
            $referenceAttributes['bio_page_id'] ?? null,
            $referenceAttributes['bio_link_id'] ?? null,
            $referenceAttributes['link_page_id'] ?? null,
            $referenceAttributes['link_page_item_id'] ?? null,
            $referenceAttributes['link_page_variant_id'] ?? null,
            $referenceAttributes['link_overlay_id'] ?? null,
        ], static fn (mixed $id): bool => $id !== null));

        if ($referenceWorkspaceId !== (int) $workspace->getKey() || $referenceCount !== 1) {
            throw new InvalidArgumentException('API analytics events require exactly one resource in the token workspace.');
        }

        if (! AnalyticsEventDefinition::query()
            ->where('workspace_id', $workspace->getKey())
            ->where('event_name', $eventName)
            ->where('is_active', true)
            ->exists()) {
            throw new InvalidArgumentException("Unsupported analytics event [{$eventName}].");
        }

        $event = $workspace->analyticsEvents()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $actor->getKey(),
            'event_name' => $eventName,
            'occurred_at' => now('UTC'),
            'visitor_hash' => null,
            'visitor_key_version' => null,
            'referrer_host' => null,
            'browser' => null,
            'platform' => null,
            'country_code' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
            ...$referenceAttributes,
        ]);

        $this->dispatchToWebhooks($event);

        return $event;
    }

    private function dispatchToWebhooks(AnalyticsEvent $event): void
    {
        try {
            $this->webhooks->dispatch($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function referenceAttributes(array $references): array
    {
        $attributes = [];
        $workspaceIds = [];
        $link = $references['link'] ?? null;
        $bioPage = $references['bioPage'] ?? null;
        $bioLink = $references['bioLink'] ?? null;
        $linkPage = $references['linkPage'] ?? null;
        $linkPageItem = $references['linkPageItem'] ?? null;
        $linkOverlay = $references['linkOverlay'] ?? null;
        $linkPageVariant = $references['linkPageVariant'] ?? null;

        if ($link !== null) {
            if (! $link instanceof Link) {
                throw new InvalidArgumentException('Analytics link reference is invalid.');
            }
            $attributes['link_id'] = $link->id;
            $workspaceIds[] = $link->workspace_id;
        }

        if ($bioPage !== null) {
            if (! $bioPage instanceof BioPage) {
                throw new InvalidArgumentException('Analytics bio page reference is invalid.');
            }
            $attributes['bio_page_id'] = $bioPage->id;
            $workspaceIds[] = $bioPage->workspace_id;
        }

        if ($bioLink !== null) {
            if (! $bioLink instanceof BioLink || ($parent = $bioLink->bioPage()->first()) === null) {
                throw new InvalidArgumentException('Analytics bio link reference is invalid.');
            }
            $attributes['bio_link_id'] = $bioLink->id;
            $workspaceIds[] = $parent->workspace_id;
        }

        if ($linkPage !== null) {
            if (! $linkPage instanceof LinkPage) {
                throw new InvalidArgumentException('Analytics link page reference is invalid.');
            }
            $attributes['link_page_id'] = $linkPage->id;
            $workspaceIds[] = $linkPage->workspace_id;
        }

        if ($linkPageItem !== null) {
            if (! $linkPageItem instanceof LinkPageItem || ($parent = $linkPageItem->linkPage()->first()) === null) {
                throw new InvalidArgumentException('Analytics link page item reference is invalid.');
            }
            $attributes['link_page_item_id'] = $linkPageItem->id;
            $workspaceIds[] = $parent->workspace_id;
        }

        if ($linkPageVariant !== null) {
            if (
                ! $linkPageVariant instanceof LinkPageVariant
                || ($parent = $linkPageVariant->linkPage()->first()) === null
            ) {
                throw new InvalidArgumentException('Analytics link page variant reference is invalid.');
            }
            if (! $linkPage instanceof LinkPage || (int) $parent->getKey() !== (int) $linkPage->getKey()) {
                throw new InvalidArgumentException('Analytics link page variant must match its link page.');
            }
            $attributes['link_page_variant_id'] = $linkPageVariant->id;
            $workspaceIds[] = $parent->workspace_id;
        }

        if ($linkOverlay !== null) {
            if (! $linkOverlay instanceof LinkOverlay) {
                throw new InvalidArgumentException('Analytics link overlay reference is invalid.');
            }
            $attributes['link_overlay_id'] = $linkOverlay->id;
            $workspaceIds[] = $linkOverlay->workspace_id;
        }

        $workspaceIds = array_values(array_unique(array_map('intval', $workspaceIds)));

        if (count($workspaceIds) !== 1 || $workspaceIds[0] < 1) {
            throw new InvalidArgumentException('Analytics references must belong to one workspace.');
        }

        $attributes['workspace_id'] = $workspaceIds[0];

        return $attributes;
    }

    private function isEnabled(): bool
    {
        if (config('app.env') !== 'production') {
            return true;
        }

        return filled(config('services.linkpilot.analytics.privacy_url'))
            && filled(config('services.linkpilot.analytics.hash_key'))
            && filled(config('services.linkpilot.analytics.hash_key_version'));
    }

    /**
     * @return array{
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     utm_term: ?string,
     *     utm_content: ?string
     * }
     */
    private function utmAttributes(Request $request): array
    {
        $attributes = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            $value = $request->query($key);
            $attributes[$key] = is_string($value) ? mb_substr($value, 0, 512) : null;
        }

        return $attributes;
    }

    private function visitorHash(Request $request): string
    {
        return hash_hmac(
            'sha256',
            "{$request->ip()}\n{$request->userAgent()}",
            $this->visitorHashKey(),
        );
    }

    private function visitorHashKey(): string
    {
        return (string) (config('services.linkpilot.analytics.hash_key') ?: config('app.key'));
    }

    private function visitorKeyVersion(): string
    {
        return (string) (config('services.linkpilot.analytics.hash_key_version') ?: 'local');
    }

    private function referrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('Referer');
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;

        return is_string($host) ? Str::lower(Str::limit($host, 253, '')) : null;
    }

    private function browser(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Other',
        };
    }

    private function platform(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Other',
        };
    }
}
