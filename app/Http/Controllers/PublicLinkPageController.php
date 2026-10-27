<?php

namespace App\Http\Controllers;

use App\Models\LinkPage;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\PublicTrackingPixelProjection;
use App\Services\Analytics\TrackingConsent;
use App\Services\LinkPages\LinkPagePresentation;
use App\Services\LinkPages\LinkPageVariantAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicLinkPageController extends Controller
{
    public function __construct(
        private readonly AnalyticsTracker $analytics,
        private readonly TrackingConsent $consent,
        private readonly LinkPagePresentation $presentation,
        private readonly LinkPageVariantAssignment $variants,
        private readonly PublicTrackingPixelProjection $pixelProjection,
    ) {}

    public function __invoke(Request $request, string $slug): Response
    {
        $linkPage = LinkPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('user:id')
            ->firstOrFail();
        $variant = $this->variants->forPageView($request, $linkPage);
        $this->variants->queueCookie($variant);

        try {
            $this->analytics->record($request, 'link_page.view', $linkPage->user, [
                'linkPage' => $linkPage,
                'linkPageVariant' => $variant,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        $privacyUrl = config('services.linkpilot.analytics.privacy_url');
        $items = $this->presentation->make($linkPage, variant: $variant);
        if (config('app.env') === 'production' && blank($privacyUrl)) {
            $items = array_values(array_filter(
                $items,
                fn (array $item): bool => $item['type'] !== 'form',
            ));
        }

        return Inertia::render('link-pages/public', [
            'title' => $linkPage->title,
            'description' => $linkPage->description,
            'slug' => $linkPage->slug,
            'template' => $linkPage->template,
            'items' => $items,
            'pixels' => $this->pixelProjection->forSurface($linkPage, $this->analytics->canTrack($request)),
            'trackingConsent' => $this->consent->choice($request),
            'privacyUrl' => $privacyUrl,
            'canGrantConsent' => $this->consent->canGrant(),
        ]);
    }
}
