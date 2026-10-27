<?php

namespace App\Http\Controllers;

use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Links\LinkAccessUnlocks;
use App\Services\Links\LinkDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicLinkController extends Controller
{
    public function __construct(
        private readonly PublicLinkHostResolver $hosts,
        private readonly LinkDeliveryService $delivery,
        private readonly LinkAccessUnlocks $unlocks,
        private readonly PublicLinkOverlayController $overlays,
    ) {}

    public function __invoke(Request $request, string $slug): RedirectResponse|Response
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

            if ($overlay !== null) {
                return $this->overlays->render($request, $link, $overlay);
            }
        }

        if ($link->delivery_mode === 'splash') {
            return Inertia::render('links/splash', [
                'slug' => $link->slug,
                'title' => $link->title,
            ]);
        }

        return redirect()->away($this->delivery->complete($request, $link), 302);
    }
}
