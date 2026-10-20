<?php

namespace App\Http\Controllers;

use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Links\LinkAccessUnlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicLinkController extends Controller
{
    public function __construct(
        private readonly PublicLinkHostResolver $hosts,
        private readonly LinkAccessUnlocks $unlocks,
    ) {}

    public function __invoke(Request $request, string $slug): RedirectResponse|Response
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

        return redirect()->away($link->destination_url, 302);
    }
}
