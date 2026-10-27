<?php

namespace App\Http\Controllers;

use App\Models\BioContentBlock;
use App\Models\BioPage;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\BioPages\BioPagePresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PublicBioContentBlockController extends Controller
{
    public function __construct(private readonly BioPagePresentation $presentation, private readonly AnalyticsTracker $analytics, private readonly TrackingConsent $consent) {}

    public function __invoke(Request $request, string $handle, BioContentBlock $bioBlock): RedirectResponse
    {
        $page = BioPage::query()->where('handle', $handle)->where('is_published', true)->firstOrFail();
        abort_unless($bioBlock->bio_page_id === $page->id && $bioBlock->type === 'social' && $this->presentation->eligible($bioBlock), 404);
        $provider = $bioBlock->content['provider'];
        $identifier = $bioBlock->content['identifier'];
        $destination = match ($provider) {
            'instagram' => "https://instagram.com/{$identifier}/", 'x' => "https://x.com/{$identifier}",
            'tiktok' => "https://www.tiktok.com/@{$identifier}", 'youtube' => "https://www.youtube.com/@{$identifier}",
            'linkedin' => "https://www.linkedin.com/in/{$identifier}/", 'github' => "https://github.com/{$identifier}",
            default => abort(404),
        };
        if ($this->consent->allows($request)) {
            try {
                $this->analytics->record($request, 'bio_page.clicked', $page->user, ['bioPage' => $page]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->away($destination);
    }
}
