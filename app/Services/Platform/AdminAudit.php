<?php

namespace App\Services\Platform;

use App\Enums\PlatformPermission;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminAudit
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(
        ?User $actor,
        PlatformPermission $permission,
        string $action,
        ?Model $subject = null,
        ?string $reason = null,
        array $changes = [],
        ?string $correlationId = null,
    ): AdminAuditLog {
        return AdminAuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'permission' => $permission->value,
            'action' => Str::limit($action, 80, ''),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'correlation_id' => $this->correlationId($correlationId),
            'reason' => $reason === null ? null : Str::limit($this->plainText($reason), 500, ''),
            'changes' => $this->redact($changes),
        ]);
    }

    private function correlationId(?string $candidate): string
    {
        $candidate = $candidate ?? request()->header('X-Request-ID');

        if (is_string($candidate) && preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::uuid();
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function redact(array $changes, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }

        $safe = [];

        foreach (array_slice($changes, 0, 20, true) as $key => $value) {
            $key = Str::limit($this->plainText((string) $key), 80, '');

            if (preg_match('/password|secret|token|authorization|cookie|session|ip_address|destination|body|path|url|email/i', $key) === 1) {
                $safe[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->redact($value, $depth + 1);

                continue;
            }

            if (is_string($value)) {
                $safe[$key] = Str::limit($this->plainText($value), 200, '');

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function plainText(string $value): string
    {
        return (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
    }
}
