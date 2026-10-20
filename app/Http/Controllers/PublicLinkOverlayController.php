<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\TrackingPixel;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Links\LinkAccessUnlocks;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class PublicLinkOverlayController extends Controller
{
    public function __construct(
        private readonly PublicLinkHostResolver $hosts,
        private readonly LinkAccessUnlocks $unlocks,
        private readonly AnalyticsTracker $analytics,
        private readonly TrackingConsent $consent,
    ) {}

    /**
     * Render the LinkPilot-controlled interstitial for an eligible link. The
     * destination is never embedded, framed, or injected here; the visitor
     * proceeds only through the continue endpoint.
     */
    public function render(Request $request, Link $link, LinkOverlay $overlay): Response
    {
        try {
            $this->analytics->record($request, 'overlay.view', $link->user, [
                'link' => $link,
                'linkOverlay' => $overlay,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        $pixels = $this->analytics->canTrack($request)
            ? $this->safePixels($overlay)
            : [];

        return Inertia::render('links/overlay', [
            'slug' => $link->slug,
            'linkTitle' => $link->title,
            'overlay' => [
                'heading' => $overlay->heading,
                'body' => $overlay->body,
                'ctaLabel' => $overlay->cta_label,
            ],
            'pixels' => $pixels,
            'trackingConsent' => $this->consent->choice($request),
            'privacyUrl' => config('services.linkpilot.analytics.privacy_url'),
        ]);
    }

    public function continue(Request $request, string $slug): Response|SymfonyResponse
    {
        $link = $this->hosts->resolveLink($request, $slug);

        abort_unless($link->isAvailableAt(now()), 404);

        if (
            $link->requiresAccessCode()
            && ! $this->unlocks->allows($request, $link)
        ) {
            return Inertia::render('links/unlock', [
                'slug' => $link->slug,
                'title' => $link->title,
            ]);
        }

        $link->clicks()->create([
            'occurred_at' => now(),
        ]);

        try {
            $this->analytics->record($request, 'link.clicked', $link->user, [
                'link' => $link,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return Inertia::location($link->destination_url);
    }

    /**
     * @return array<int, array{provider: string, identifier: string}>
     */
    private function safePixels(LinkOverlay $overlay): array
    {
        return $overlay->trackingPixels()
            ->where('tracking_pixels.is_active', true)
            ->orderBy('tracking_pixels.name')
            ->get()
            ->map(fn (TrackingPixel $pixel): array => [
                'provider' => $pixel->provider,
                'identifier' => $pixel->provider_id,
            ])
            ->values()
            ->all();
    }
}
