<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class QrCodeAnalytics
{
    /** @return array{days: int, totalScans: int, daily: list<array{date: string, scans: int}>} */
    public function forWorkspace(Workspace $workspace, int $days): array
    {
        return $this->series(
            QrScan::query()->whereHas('qrCode', fn (Builder $query) => $query->where('workspace_id', $workspace->id)),
            $days,
        );
    }

    /** @return array{days: int, totalScans: int, daily: list<array{date: string, scans: int}>} */
    public function forCampaign(QrCode $qrCode, int $days): array
    {
        return $this->series($qrCode->scans()->getQuery(), $days);
    }

    /** @param Builder<QrScan> $scans
     * @return array{days: int, totalScans: int, daily: list<array{date: string, scans: int}>}
     */
    private function series(Builder $scans, int $days): array
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

        return [
            'days' => $days,
            'totalScans' => (clone $window)->count(),
            'daily' => $daily,
        ];
    }
}
