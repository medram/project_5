<?php

namespace App\Http\Controllers;

use App\Models\BioLink;
use App\Models\BioPage;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\BioPages\BioPagePresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PublicBioLinkController extends Controller
{
    public function __construct(private readonly AnalyticsTracker $analytics, private readonly TrackingConsent $consent, private readonly BioPagePresentation $presentation) {}

    public function __invoke(Request $request, string $handle, BioLink $bioLink): RedirectResponse
    {
        $bioPage = BioPage::query()->where('handle', $handle)->where('is_published', true)->firstOrFail();
        abort_unless($bioLink->bio_page_id === $bioPage->id && $this->presentation->eligible($bioLink), 404);
        if ($this->consent->allows($request)) {
            try {
                $this->analytics->record($request, 'bio_page.clicked', $bioPage->user, ['bioPage' => $bioPage, 'bioLink' => $bioLink]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->away($bioLink->destination_url);
    }
}
