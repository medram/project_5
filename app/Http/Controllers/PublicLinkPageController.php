<?php

namespace App\Http\Controllers;

use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\TrackingPixel;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicLinkPageController extends Controller
{
    public function __construct(
        private readonly AnalyticsTracker $analytics,
        private readonly TrackingConsent $consent,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $linkPage = LinkPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with([
                'user:id',
                'items' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('position'),
                'trackingPixels' => fn ($query) => $query
                    ->where('tracking_pixels.is_active', true)
                    ->orderBy('tracking_pixels.name'),
            ])
            ->firstOrFail();

        try {
            $this->analytics->record($request, 'link_page.view', $linkPage->user, [
                'linkPage' => $linkPage,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        $pixels = $this->analytics->canTrack($request)
            ? $linkPage->trackingPixels
                ->map(fn (TrackingPixel $pixel): array => [
                    'provider' => $pixel->provider,
                    'identifier' => $pixel->provider_id,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('link-pages/public', [
            'name' => $linkPage->name,
            'title' => $linkPage->title,
            'description' => $linkPage->description,
            'slug' => $linkPage->slug,
            'items' => $linkPage->items
                ->map(fn (LinkPageItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'url' => "/pages/{$linkPage->slug}/items/{$item->id}",
                ])
                ->values()
                ->all(),
            'pixels' => $pixels,
            'trackingConsent' => $this->consent->choice($request),
            'privacyUrl' => config('services.linkpilot.analytics.privacy_url'),
        ]);
    }
}
