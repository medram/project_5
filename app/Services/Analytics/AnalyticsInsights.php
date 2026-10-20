<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsInsights
{
    /**
     * @return array<int, array{total: int, daily: list<array{date: string, events: int}>}>
     */
    public function forAccount(User $user): array
    {
        return $this->windows(
            AnalyticsEvent::query()->where('user_id', $user->id),
        );
    }

    /**
     * @return array<int, array{total: int, daily: list<array{date: string, events: int}>}>
     */
    public function forLink(Link $link): array
    {
        return $this->windows(
            AnalyticsEvent::query()
                ->where('user_id', $link->user_id)
                ->where('link_id', $link->id),
        );
    }

    /**
     * @param  Builder<AnalyticsEvent>  $events
     * @return array<int, array{total: int, daily: list<array{date: string, events: int}>}>
     */
    private function windows(Builder $events): array
    {
        return collect([7, 30, 90])
            ->mapWithKeys(fn (int $days): array => [$days => $this->window($events, $days)])
            ->all();
    }

    /**
     * @param  Builder<AnalyticsEvent>  $events
     * @return array{total: int, daily: list<array{date: string, events: int}>}
     */
    private function window(Builder $events, int $days): array
    {
        $end = now('UTC')->startOfDay()->addDay();
        $start = $end->copy()->subDays($days);
        $counts = (clone $events)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->get(['occurred_at'])
            ->countBy(
                fn (AnalyticsEvent $event): string => $event->occurred_at
                    ->setTimezone('UTC')
                    ->toDateString(),
            );
        $daily = collect(range(0, $days - 1))
            ->map(function (int $offset) use ($start, $counts): array {
                $date = $start->copy()->addDays($offset)->toDateString();

                return [
                    'date' => $date,
                    'events' => $counts->get($date, 0),
                ];
            })
            ->all();

        return [
            'total' => $counts->sum(),
            'daily' => $daily,
        ];
    }
}
