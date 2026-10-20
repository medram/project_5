<?php

namespace App\Console\Commands;

use App\Models\CustomDomain;
use App\Services\Domains\DomainVerificationService;
use Illuminate\Console\Command;

class VerifyCustomDomains extends Command
{
    /**
     * @var string
     */
    protected $signature = 'linkpilot:verify-custom-domains';

    /**
     * @var string
     */
    protected $description = 'Recheck every retained LinkPilot custom domain';

    public function handle(DomainVerificationService $verification): int
    {
        $checked = 0;

        foreach (CustomDomain::query()->orderBy('id')->cursor() as $domain) {
            $verification->verify($domain);
            $checked++;
        }

        $this->components->info("Rechecked {$checked} custom domains.");

        return self::SUCCESS;
    }
}
