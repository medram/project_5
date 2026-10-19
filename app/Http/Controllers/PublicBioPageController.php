<?php

namespace App\Http\Controllers;

use App\Models\BioLink;
use App\Models\BioPage;
use Inertia\Inertia;
use Inertia\Response;

class PublicBioPageController extends Controller
{
    public function __invoke(string $handle): Response
    {
        $bioPage = BioPage::query()
            ->where('handle', $handle)
            ->where('is_published', true)
            ->with([
                'bioLinks' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('position'),
            ])
            ->firstOrFail();

        return Inertia::render('bio-pages/public', [
            'displayName' => $bioPage->display_name,
            'bio' => $bioPage->bio,
            'handle' => $bioPage->handle,
            'links' => $bioPage->bioLinks
                ->map(fn (BioLink $bioLink): array => [
                    'id' => $bioLink->id,
                    'title' => $bioLink->title,
                    'destinationUrl' => $bioLink->destination_url,
                ])
                ->values(),
        ]);
    }
}
