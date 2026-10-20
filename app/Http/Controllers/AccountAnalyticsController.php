<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsInsights;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountAnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsInsights $analytics) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('analytics/index', [
            'windows' => $this->analytics->forAccount($request->user()),
        ]);
    }
}
