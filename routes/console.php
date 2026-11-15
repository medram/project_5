<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('linkpilot:verify-custom-domains')->daily();
Schedule::command('linkpilot:prune-analytics')->daily();
Schedule::command('linkpilot:prune-content-media')->daily();
Schedule::command('linkpilot:prune-link-page-leads')->daily();
Schedule::command('linkpilot:purge-deleted-accounts')->daily();
Schedule::command('linkpilot:prune-operation-logs')->daily();
