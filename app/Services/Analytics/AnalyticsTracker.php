<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AnalyticsTracker
{
    private const EVENT_NAMES = [
        'link.clicked',
        'overlay.view',
        'link_page.view',
        'link_page.clicked',
        'bio_page.view',
        'bio_page.clicked',
    ];

    public function __construct(private readonly TrackingConsent $consent) {}

    /**
     * @param  array<string, mixed>  $references
     */
    public function record(
        Request $request,
        string $eventName,
        User $user,
        array $references = [],
    ): ?AnalyticsEvent {
        if (! in_array($eventName, self::EVENT_NAMES, true)) {
            throw new InvalidArgumentException("Unsupported analytics event [{$eventName}].");
        }

        if (! $this->isEnabled() || ! $this->consent->allows($request)) {
            return null;
        }

        return $user->analyticsEvents()->create([
            'uuid' => (string) Str::uuid(),
            'event_name' => $eventName,
            'occurred_at' => now('UTC'),
            'visitor_hash' => $this->visitorHash($request),
            'visitor_key_version' => $this->visitorKeyVersion(),
            'referrer_host' => $this->referrerHost($request),
            'browser' => $this->browser($request),
            'platform' => $this->platform($request),
            ...$this->referenceAttributes($user, $references),
        ]);
    }

    /**
     * @param  array<string, mixed>  $references
     * @return array<string, int>
     */
    private function referenceAttributes(User $user, array $references): array
    {
        $attributes = [];
        $link = $references['link'] ?? null;
        $bioPage = $references['bioPage'] ?? null;
        $bioLink = $references['bioLink'] ?? null;

        if ($link !== null) {
            if (! $link instanceof Link || $link->user_id !== $user->id) {
                throw new InvalidArgumentException('Analytics link reference must belong to the account.');
            }

            $attributes['link_id'] = $link->id;
        }

        if ($bioPage !== null) {
            if (! $bioPage instanceof BioPage || $bioPage->user_id !== $user->id) {
                throw new InvalidArgumentException('Analytics bio page reference must belong to the account.');
            }

            $attributes['bio_page_id'] = $bioPage->id;
        }

        if ($bioLink !== null) {
            if (! $bioLink instanceof BioLink) {
                throw new InvalidArgumentException('Analytics bio link reference is invalid.');
            }

            $bioLinkOwnerId = $bioLink->bioPage()->value('user_id');

            if ((int) $bioLinkOwnerId !== $user->id) {
                throw new InvalidArgumentException('Analytics bio link reference must belong to the account.');
            }

            $attributes['bio_link_id'] = $bioLink->id;
        }

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
