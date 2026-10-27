<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Models\LinkOverlay;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\PublicTrackingPixelProjection;
use App\Services\Analytics\TrackingConsent;
use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Links\LinkAccessUnlocks;
use App\Services\Links\LinkDeliveryService;
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
        private readonly LinkDeliveryService $delivery,
        private readonly AnalyticsTracker $analytics,
        private readonly TrackingConsent $consent,
        private readonly PublicTrackingPixelProjection $pixelProjection,
    ) {}

    /**
     * Render the LinkPilot-controlled interstitial for an eligible link. The
     * destination is never embedded, framed, or injected here; the visitor
     * proceeds only through the continue endpoint.
     */
    public function render(Request $request, Link $link, LinkOverlay $overlay): Response
    {
        $this->delivery->assertAvailable($link);
        $remainingDelaySeconds = $this->remainingDelaySeconds($request, $link, $overlay);
        $skipView = (bool) $request->session()->pull($this->skipViewSessionKey($link, $overlay), false);
        $viewRecorded = (bool) $request->session()->get($this->viewRecordedSessionKey($link, $overlay), false);

        if (! $skipView) {
            $viewRecorded = false;
            try {
                $this->analytics->record($request, 'overlay.view', $link->user, [
                    'link' => $link,
                    'linkOverlay' => $overlay,
                ]);
                $viewRecorded = $this->analytics->canTrack($request);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $request->session()->put($this->viewRecordedSessionKey($link, $overlay), $viewRecorded);

        return $this->overlayPage($request, $link, $overlay, $remainingDelaySeconds, ! $skipView);
    }

    public function continue(Request $request, string $slug): Response|SymfonyResponse
    {
        $link = $this->hosts->resolveLink($request, $slug);
        $this->delivery->assertAvailable($link);

        if (
            $link->requiresAccessCode()
            && ! $this->unlocks->allows($request, $link)
        ) {
            return Inertia::render('links/unlock', [
                'slug' => $link->slug,
                'title' => $link->title,
            ]);
        }

        if ($link->delivery_mode === 'overlay') {
            $overlay = $link->activeLinkOverlay();
            abort_unless($overlay !== null, 404);

            $remainingDelaySeconds = $this->remainingDelaySeconds($request, $link, $overlay);
            if ($remainingDelaySeconds > 0) {
                if ($request->session()->get($this->viewRecordedSessionKey($link, $overlay), false)) {
                    $request->session()->put($this->skipViewSessionKey($link, $overlay), true);
                }

                return redirect()->to('/'.$link->slug);
            }

            $request->session()->forget([
                $this->delaySessionKey($link, $overlay),
                $this->viewRecordedSessionKey($link, $overlay),
                $this->skipViewSessionKey($link, $overlay),
            ]);
        }

        return Inertia::location($this->delivery->complete($request, $link));
    }

    private function overlayPage(
        Request $request,
        Link $link,
        LinkOverlay $overlay,
        int $remainingDelaySeconds,
        bool $includePixels,
    ): Response {
        $pixels = $this->pixelProjection->forSurface(
            $overlay,
            $includePixels && $this->analytics->canTrack($request),
        );

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
            'canGrantConsent' => $this->consent->canGrant(),
            'remainingDelaySeconds' => $remainingDelaySeconds,
        ]);
    }

    private function remainingDelaySeconds(Request $request, Link $link, LinkOverlay $overlay): int
    {
        $key = $this->delaySessionKey($link, $overlay);
        $now = now('UTC')->valueOf();
        $deadline = $request->session()->get($key);

        if (! is_int($deadline) || $deadline < 0) {
            $deadline = $now + ((int) $overlay->display_delay_seconds * 1000);
            $request->session()->put($key, $deadline);
        }

        return max(0, (int) ceil(($deadline - $now) / 1000));
    }

    private function delaySessionKey(Link $link, LinkOverlay $overlay): string
    {
        return "linkpilot.overlay_continue.{$link->getKey()}.{$overlay->getKey()}";
    }

    private function viewRecordedSessionKey(Link $link, LinkOverlay $overlay): string
    {
        return "linkpilot.overlay_view_recorded.{$link->getKey()}.{$overlay->getKey()}";
    }

    private function skipViewSessionKey(Link $link, LinkOverlay $overlay): string
    {
        return "linkpilot.overlay_skip_view.{$link->getKey()}.{$overlay->getKey()}";
    }

}
