<?php

namespace App\Http\Controllers;

use App\Models\BioPage;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\PublicTrackingPixelProjection;
use App\Services\Analytics\TrackingConsent;
use App\Services\BioPages\BioPagePresentation;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicBioPageController extends Controller
{
    public function __construct(
        private readonly AnalyticsTracker $analytics,
        private readonly TrackingConsent $consent,
        private readonly PublicTrackingPixelProjection $pixelProjection,
    ) {}

    public function __invoke(Request $request, string $handle): Response
    {
        $bioPage = BioPage::query()
            ->where('handle', $handle)
            ->where('is_published', true)
            ->with('user:id')
            ->firstOrFail();

        try {
            $this->analytics->record($request, 'bio_page.view', $bioPage->user, [
                'bioPage' => $bioPage,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return Inertia::render('bio-pages/public', [
            'presentation' => app(BioPagePresentation::class)->make($bioPage),
            'pixels' => $this->pixelProjection->forSurface($bioPage, $this->analytics->canTrack($request)),
            'trackingConsent' => $this->consent->effectiveChoice($request),
            'privacyUrl' => config('services.linkpilot.analytics.privacy_url'),
            'canGrantConsent' => $this->consent->canGrant(),
            'branding' => $this->branding(),
        ]);
    }

    /**
     * @return array{label: string, logo: string|null}|null
     */
    private function branding(): ?array
    {
        $settings = app(PlatformSettings::class)->resolved();
        $biolinks = $settings['biolinks'] ?? [];

        if (! (bool) ($biolinks['branding_enabled'] ?? false)) {
            return null;
        }

        $mediaId = is_numeric($biolinks['branding_media_id'] ?? null) ? (int) $biolinks['branding_media_id'] : 0;

        return [
            'label' => (string) ($settings['general']['site_name'] ?? config('app.name')),
            'logo' => $mediaId > 0 ? route('platform.media', ['media' => $mediaId]) : null,
        ];
    }
}
