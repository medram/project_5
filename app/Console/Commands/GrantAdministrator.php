<?php

namespace App\Console\Commands;

use App\Enums\PlatformRoleCode;
use App\Models\User;
use App\Services\Platform\PlatformRoleAssignments;
use Illuminate\Console\Command;

class GrantAdministrator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'linkpilot:grant-admin {email : Email address of the existing account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant the administrator role to an existing LinkPilot account';

    public function handle(): int
    {
        $argument = $this->argument('email');
        $email = trim(is_string($argument) ? $argument : '');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No LinkPilot account exists for {$email}.");

            return self::FAILURE;
        }

        $changed = app(PlatformRoleAssignments::class)->grantSystem($user, PlatformRoleCode::SuperAdmin);

        $this->components->info($changed
            ? "Granted super administrator access to {$user->email}."
            : "{$user->email} already has super administrator access.");

        return self::SUCCESS;
    }
}
