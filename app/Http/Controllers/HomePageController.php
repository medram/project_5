<?php

namespace App\Http\Controllers;

use App\Services\Settings\PlatformSettings;
use Inertia\Inertia;
use Inertia\Response;

class HomePageController extends Controller
{
    public function __invoke(PlatformSettings $settings): Response|\Illuminate\Http\RedirectResponse
    {
        $resolved = $settings->resolved();
        $homepage = $resolved['landing']['homepage'] ?? 'home';
        $pricing = (bool) ($resolved['landing']['pricing_enabled'] ?? true);

        if ($homepage === 'pricing' && $pricing) {
            return redirect()->route('pricing');
        }

        if ($homepage === 'login') {
            return redirect()->route('login');
        }

        return Inertia::render('welcome');
    }
}
