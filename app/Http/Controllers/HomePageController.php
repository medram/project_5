<?php

namespace App\Http\Controllers;

use App\Services\Analytics\TrackingConsent;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomePageController extends Controller
{
    public function __invoke(
        PlatformSettings $settings,
        TrackingConsent $consent,
        Request $request,
    ): Response|RedirectResponse {
        $resolved = $settings->resolved();
        $homepage = $resolved['landing']['homepage'] ?? 'home';
        $pricing = (bool) ($resolved['landing']['pricing_enabled'] ?? true);

        if ($homepage === 'pricing' && $pricing) {
            return redirect()->route('pricing');
        }

        if ($homepage === 'login') {
            return redirect()->route('login');
        }

        return Inertia::render('welcome', [
            'trackingConsent' => $consent->effectiveChoice($request),
            'privacyUrl' => config('services.linkpilot.analytics.privacy_url'),
            'canGrantConsent' => $consent->canGrant(),
        ]);
    }
}
