<?php

namespace App\Http\Controllers;

use App\Models\BioLink;
use App\Models\BioPage;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PublicBioLinkController extends Controller
{
    public function __construct(private readonly AnalyticsTracker $analytics) {}

    public function __invoke(Request $request, string $handle, BioLink $bioLink): RedirectResponse
    {
        $bioPage = BioPage::query()
            ->where('handle', $handle)
            ->where('is_published', true)
            ->firstOrFail();

        abort_unless(
            $bioLink->bio_page_id === $bioPage->id && $bioLink->is_active,
            404,
        );

        try {
            $this->analytics->record($request, 'bio_page.clicked', $bioPage->user, [
                'bioPage' => $bioPage,
                'bioLink' => $bioLink,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->away($bioLink->destination_url);
    }
}
