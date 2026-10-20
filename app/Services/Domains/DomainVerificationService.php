<?php

namespace App\Services\Domains;

use App\Models\CustomDomain;
use Illuminate\Support\Str;
use Throwable;

class DomainVerificationService
{
    public function __construct(
        private readonly DnsTxtLookup $dns,
        private readonly HttpsDomainChallengeProbe $https,
    ) {}

    public function verify(CustomDomain $domain): CustomDomain
    {
        $wasActive = $domain->status === 'active';
        $expectedTxtRecord = "linkpilot={$domain->verification_token}";

        try {
            if (! in_array($expectedTxtRecord, $this->dns->recordsFor("_linkpilot-verify.{$domain->hostname}"), true)) {
                return $this->recordCheck(
                    $domain,
                    $wasActive ? 'failed' : 'pending_dns',
                    $wasActive ? 'The DNS TXT verification record is no longer present.' : null,
                );
            }

            if (! $this->https->respondsWithToken($domain->hostname, $domain->verification_token)) {
                return $this->recordCheck(
                    $domain,
                    $wasActive ? 'failed' : 'pending_tls',
                    $wasActive ? 'The HTTPS verification endpoint is no longer reachable.' : null,
                );
            }
        } catch (Throwable $exception) {
            return $this->recordCheck(
                $domain,
                'failed',
                Str::limit($exception->getMessage(), 500, ''),
            );
        }

        $domain->forceFill([
            'status' => 'active',
            'verified_at' => $domain->verified_at ?? now(),
            'last_checked_at' => now(),
            'failure_reason' => null,
        ])->save();

        return $domain->refresh();
    }

    public function reissueToken(CustomDomain $domain): CustomDomain
    {
        $domain->forceFill([
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'pending_dns',
            'verified_at' => null,
            'last_checked_at' => null,
            'failure_reason' => null,
        ])->save();

        return $domain->refresh();
    }

    private function recordCheck(
        CustomDomain $domain,
        string $status,
        ?string $failureReason,
    ): CustomDomain {
        $domain->forceFill([
            'status' => $status,
            'last_checked_at' => now(),
            'failure_reason' => $failureReason,
        ])->save();

        return $domain->refresh();
    }
}
