<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminInsightsRequest;
use App\Services\Admin\AdminInsights;
use Inertia\Inertia;
use Inertia\Response;

class AdminInsightsController extends Controller
{
    public function __construct(private readonly AdminInsights $insights) {}

    public function index(AdminInsightsRequest $request): Response
    {
        $range = $request->range();

        return Inertia::render('admin/insights/index', [
            'insights' => $this->insights->platform($range),
        ]);
    }

    public function visitors(AdminInsightsRequest $request): Response
    {
        $range = $request->range();

        return Inertia::render('admin/insights/visitors', [
            'insights' => $this->insights->visitors($range),
        ]);
    }
}