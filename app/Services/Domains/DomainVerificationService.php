<?php

namespace App\Services\Domains;

use App\Models\CustomDomain;
use App\Models\Workspace;
use App\Services\Notifications\WorkspaceNotificationService;
use DateTimeInterface;
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
        $observedToken = $domain->verification_token;
        $observedStatus = $domain->status;
        $wasActive = $observedStatus === 'active';
        $expectedTxtRecord = "linkpilot={$observedToken}";

        try {
            if (! in_array($expectedTxtRecord, $this->dns->recordsFor("_linkpilot-verify.{$domain->hostname}"), true)) {
                return $this->recordCheck(
                    $domain,
                    $observedToken,
                    $observedStatus,
                    $wasActive ? 'failed' : 'pending_dns',
                    $wasActive ? 'The DNS TXT verification record is no longer present.' : null,
                );
            }

            if (! $this->https->respondsWithToken($domain->hostname, $observedToken)) {
                return $this->recordCheck(
                    $domain,
                    $observedToken,
                    $observedStatus,
                    $wasActive ? 'failed' : 'pending_tls',
                    $wasActive ? 'The HTTPS verification endpoint is no longer reachable.' : null,
                );
            }
        } catch (Throwable $exception) {
            return $this->recordCheck(
                $domain,
                $observedToken,
                $observedStatus,
                'failed',
                Str::limit($exception->getMessage(), 500, ''),
            );
        }

        return $this->recordCheck(
            $domain,
            $observedToken,
            $observedStatus,
            'active',
            null,
            $domain->verified_at ?? now(),
        );
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
        string $observedToken,
        string $observedStatus,
        string $status,
        ?string $failureReason,
        ?DateTimeInterface $verifiedAt = null,
    ): CustomDomain {
        $attributes = [
            'status' => $status,
            'last_checked_at' => now(),
            'failure_reason' => $failureReason,
        ];

        if ($status === 'active') {
            $attributes['verified_at'] = $verifiedAt;
        }

        $updated = CustomDomain::query()
            ->whereKey($domain->getKey())
            ->where('verification_token', $observedToken)
            ->where('status', $observedStatus)
            ->update($attributes);

        $domain = $domain->refresh();

        if ($updated === 1 && $status === 'failed' && $observedStatus !== 'failed') {
            $workspace = Workspace::query()->find($domain->workspace_id);

            if ($workspace !== null) {
                app(WorkspaceNotificationService::class)->notifyManagers(
                    $workspace,
                    'domains',
                    'Custom domain verification failed',
                    'A custom domain failed verification. Review its DNS and HTTPS configuration.',
                    'custom_domain',
                    (int) $domain->getKey(),
                );
            }
        }

        return $domain;
    }
}
