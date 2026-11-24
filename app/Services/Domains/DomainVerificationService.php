<?php

namespace App\Services\Domains;

use App\Models\CustomDomain;
use App\Models\Workspace;
use App\Services\Notifications\WorkspaceNotificationService;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;

class DomainVerificationService
{
    public function __construct(
        private readonly DnsTxtLookup $dns,
        private readonly HttpsDomainChallengeProbe $https,
    ) {}

    public function verify(CustomDomain $domain, ?Closure $apply = null): CustomDomain
    {
        $observedToken = $domain->verification_token;
        $observedStatus = $domain->status;
        $wasActive = $observedStatus === 'active';
        $verifiedAt = null;

        try {
            if (! in_array("linkpilot={$observedToken}", $this->dns->recordsFor("_linkpilot-verify.{$domain->hostname}"), true)) {
                $status = $wasActive ? 'failed' : 'pending_dns';
                $failureReason = $wasActive ? 'The DNS TXT verification record is no longer present.' : null;
            } elseif (! $this->https->respondsWithToken($domain->hostname, $observedToken)) {
                $status = $wasActive ? 'failed' : 'pending_tls';
                $failureReason = $wasActive ? 'The HTTPS verification endpoint is no longer reachable.' : null;
            } else {
                $status = 'active';
                $failureReason = null;
                $verifiedAt = $domain->verified_at ?? now();
            }
        } catch (Throwable $exception) {
            $status = 'failed';
            $failureReason = Str::limit($exception->getMessage(), 500, '');
        }

        return $this->recordCheck(
            $domain, $observedToken, $observedStatus, $status, $failureReason, $verifiedAt, $apply,
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
        ?Closure $apply = null,
    ): CustomDomain {
        if ($apply !== null) {
            return $apply(fn () => $this->recordCheck(
                $domain, $observedToken, $observedStatus, $status, $failureReason, $verifiedAt,
            ));
        }

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
