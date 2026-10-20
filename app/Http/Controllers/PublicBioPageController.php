<?php

namespace App\Http\Controllers;

use App\Models\BioLink;
use App\Models\BioPage;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicBioPageController extends Controller
{
    public function __construct(
        private readonly AnalyticsTracker $analytics,
        private readonly TrackingConsent $consent,
    ) {}

    public function __invoke(Request $request, string $handle): Response
    {
        $bioPage = BioPage::query()
            ->where('handle', $handle)
            ->where('is_published', true)
            ->with([
                'user:id',
                'bioLinks' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('position'),
            ])
            ->firstOrFail();

        try {
            $this->analytics->record($request, 'bio_page.view', $bioPage->user, [
                'bioPage' => $bioPage,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return Inertia::render('bio-pages/public', [
            'displayName' => $bioPage->display_name,
            'bio' => $bioPage->bio,
            'handle' => $bioPage->handle,
            'links' => $bioPage->bioLinks
                ->map(fn (BioLink $bioLink): array => [
                    'id' => $bioLink->id,
                    'title' => $bioLink->title,
                    'url' => "/bio/{$bioPage->handle}/links/{$bioLink->id}",
                ])
                ->values(),
            'trackingConsent' => $this->consent->choice($request),
            'privacyUrl' => config('services.linkpilot.analytics.privacy_url'),
        ]);
    }
}
