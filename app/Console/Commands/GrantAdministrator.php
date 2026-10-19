<?php

namespace App\Console\Commands;

use App\Models\User;
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
        $email = trim((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No LinkPilot account exists for {$email}.");

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->components->info("Granted administrator access to {$user->email}.");

        return self::SUCCESS;
    }
}
