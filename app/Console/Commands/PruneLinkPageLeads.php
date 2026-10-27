<?php

namespace App\Console\Commands;

use App\Models\LinkPageLead;
use Illuminate\Console\Command;

class PruneLinkPageLeads extends Command
{
    protected $signature = 'linkpilot:prune-link-page-leads';

    protected $description = 'Permanently delete expired link-page lead responses';

    public function handle(): int
    {
        $deleted = LinkPageLead::query()
            ->where('retention_expires_at', '<=', now('UTC'))
            ->delete();

        $this->info("Pruned {$deleted} expired link-page lead response(s).");

        return self::SUCCESS;
    }
}
