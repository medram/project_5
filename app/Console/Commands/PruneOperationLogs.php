<?php

namespace App\Console\Commands;

use App\Models\OperationLog;
use App\Services\Settings\PlatformSettings;
use Illuminate\Console\Command;

class PruneOperationLogs extends Command
{
    protected $signature = 'linkpilot:prune-operation-logs {--dry-run : Report the number of rows that would be deleted without deleting them}';

    protected $description = 'Prune application-owned operational logs according to the configured retention window';

    public function handle(PlatformSettings $settings): int
    {
        $settings = $settings->resolved();
        $days = (int) data_get($settings, 'system.log_retention_days', 30);
        $days = max(1, min(3650, $days));
        $cutoff = now()->subDays($days);
        $query = OperationLog::query()->where('created_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $this->info("Would prune {$query->count()} operation log rows older than {$days} days.");

            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Pruned {$deleted} operation log rows older than {$days} days.");

        return self::SUCCESS;
    }
}
