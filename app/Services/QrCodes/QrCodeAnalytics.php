<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\Workspace;
use App\Services\Analytics\RequestDimensions;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class QrCodeAnalytics
{
    /**
     * @return array{
     *     days: int,
     *     totalScans: int,
     *     countryCount: int,
     *     browserCount: int,
     *     deviceCount: int,
     *     daily: list<array{date: string, scans: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>,
     *     topDevices: list<array{name: string, count: int}>
     * }
     */
    public function forWorkspace(Workspace $workspace, int $days): array
    {
        return $this->result(
            QrScan::query()->whereHas('qrCode', fn (Builder $query) => $query->where('workspace_id', $workspace->id)),
            $days,
        );
    }

    /**
     * @return array{
     *     days: int,
     *     totalScans: int,
     *     countryCount: int,
     *     browserCount: int,
     *     deviceCount: int,
     *     daily: list<array{date: string, scans: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>,
     *     topDevices: list<array{name: string, count: int}>
     * }
     */
    public function forCampaign(QrCode $qrCode, int $days): array
    {
        return $this->result(
            QrScan::query()->where('qr_code_id', $qrCode->getKey()),
            $days,
        );
    }

    /**
     * @param  Builder<QrScan>  $scans
     * @return array{
     *     days: int,
     *     totalScans: int,
     *     countryCount: int,
     *     browserCount: int,
     *     deviceCount: int,
     *     daily: list<array{date: string, scans: int}>,
     *     topReferrers: list<array{name: string, count: int}>,
     *     topCountries: list<array{name: string, count: int}>,
     *     topBrowsers: list<array{name: string, count: int}>,
     *     topPlatforms: list<array{name: string, count: int}>,
     *     topDevices: list<array{name: string, count: int}>
     * }
     */
    private function result(Builder $scans, int $days): array
    {
        if (! in_array($days, [7, 30, 90], true)) {
            throw new InvalidArgumentException("Unsupported QR analytics window [{$days}].");
        }

        $end = now('UTC')->startOfDay()->addDay();
        $start = $end->copy()->subDays($days);
        $window = (clone $scans)
            ->where('scanned_at', '>=', $start)
            ->where('scanned_at', '<', $end);
        $counts = (clone $window)
            ->selectRaw('DATE(scanned_at) as aggregate_date, COUNT(*) as aggregate_count')
            ->groupByRaw('DATE(scanned_at)')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) $row->getAttribute('aggregate_date') => (int) $row->getAttribute('aggregate_count'),
            ])
            ->all();
        $daily = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $date = $start->copy()->addDays($offset)->toDateString();
            $daily[] = ['date' => $date, 'scans' => $counts[$date] ?? 0];
        }

        $countries = $this->groupedCounts($window, 'country_code');
        $browsers = $this->groupedCounts($window, 'browser');
        $platforms = $this->groupedCounts($window, 'platform');
        $devices = $this->deviceCounts($platforms);

        return [
            'days' => $days,
            'totalScans' => (clone $window)->count(),
            'countryCount' => count($countries),
            'browserCount' => count($browsers),
            'deviceCount' => count($devices),
            'daily' => $daily,
            'topReferrers' => $this->groupedCounts($window, 'referrer_host', 5),
            'topCountries' => array_slice($countries, 0, 5),
            'topBrowsers' => array_slice($browsers, 0, 5),
            'topPlatforms' => array_slice($platforms, 0, 5),
            'topDevices' => array_slice($devices, 0, 5),
        ];
    }

    /**
     * @param  Builder<QrScan>  $scans
     * @return list<array{name: string, count: int}>
     */
    private function groupedCounts(Builder $scans, string $column, ?int $limit = null): array
    {
        $query = (clone $scans)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->selectRaw('COUNT(*) as aggregate_count')
            ->groupBy($column)
            ->orderByDesc('aggregate_count')
            ->orderBy($column);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $rows = [];

        foreach ($query->get() as $scan) {
            $rows[] = [
                'name' => (string) $scan->getAttribute($column),
                'count' => (int) $scan->getAttribute('aggregate_count'),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, count: int}>  $platforms
     * @return list<array{name: string, count: int}>
     */
    private function deviceCounts(array $platforms): array
    {
        $counts = [];

        foreach ($platforms as $platform) {
            $device = RequestDimensions::device($platform['name']);

            if ($device !== null) {
                $counts[$device] = ($counts[$device] ?? 0) + $platform['count'];
            }
        }

        $devices = [];

        foreach ($counts as $name => $count) {
            $devices[] = ['name' => $name, 'count' => $count];
        }

        usort(
            $devices,
            fn (array $left, array $right): int => ($right['count'] <=> $left['count'])
                ?: strcmp($left['name'], $right['name']),
        );

        return $devices;
    }
}
