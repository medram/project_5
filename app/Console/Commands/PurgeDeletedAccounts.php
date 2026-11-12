<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Accounts\PurgeAccount;
use Illuminate\Console\Command;

class PurgeDeletedAccounts extends Command
{
    protected $signature = 'linkpilot:purge-deleted-accounts';

    protected $description = 'Permanently purge accounts whose 30-day deletion window has elapsed';

    public function handle(PurgeAccount $purge): int
    {
        $purged = 0;
        $skipped = 0;

        User::query()
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($purge, &$purged, &$skipped): void {
                foreach ($users as $user) {
                    if ($purge->purge($user)) {
                        $purged++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Purged {$purged} accounts; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
