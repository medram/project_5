<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Services\Settings\PlatformSettings;
use Illuminate\Console\Command;

class PruneAnalyticsEvents extends Command
{
    protected $signature = 'linkpilot:prune-analytics';

    protected $description = 'Delete analytics events that have exceeded the retention window';

    public function handle(): int
    {
        $settings = app(PlatformSettings::class);
        $retentionDays = $settings->hasStored('analytics', 'retention_days')
            ? (int) ($settings->resolved()['analytics']['retention_days'] ?? 90)
            : (int) config('services.linkpilot.analytics.retention_days', 90);
        $deleted = AnalyticsEvent::query()
            ->where('occurred_at', '<', now('UTC')->subDays($retentionDays))
            ->delete();

        $this->info("Pruned {$deleted} analytics events.");

        return self::SUCCESS;
    }
}
