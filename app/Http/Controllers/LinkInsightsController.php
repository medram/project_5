<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Models\Link;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkInsightsController extends Controller
{
    public function __invoke(Link $link): Response
    {
        Gate::authorize('view', $link);

        $timezone = config('app.timezone', 'UTC');
        $today = now($timezone)->startOfDay();
        $windowStart = $today->copy()->subDays(13);
        $windowEnd = $today->copy()->addDay();

        $clickCounts = $link->clicks()
            ->where('occurred_at', '>=', $windowStart)
            ->where('occurred_at', '<', $windowEnd)
            ->get(['occurred_at'])
            ->groupBy(fn (Click $click): string => $click->occurred_at->setTimezone($timezone)->toDateString())
            ->map->count();

        $dailyClicks = collect(range(0, 13))
            ->map(function (int $offset) use ($windowStart, $clickCounts): array {
                $date = $windowStart->copy()->addDays($offset)->toDateString();

                return [
                    'date' => $date,
                    'clicks' => $clickCounts->get($date, 0),
                ];
            })
            ->all();

        return Inertia::render('links/insights', [
            'link' => $link,
            'totalClicks' => $link->clicks()->count(),
            'dailyClicks' => $dailyClicks,
        ]);
    }
}
