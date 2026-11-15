<?php

namespace App\Services\Logging;

use App\Models\OperationLog;
use Illuminate\Support\Str;

class OperationLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $level, string $channel, string $event, string $message, array $context = []): OperationLog
    {
        return OperationLog::query()->create([
            'level' => in_array($level, ['debug', 'info', 'warning', 'error', 'critical'], true) ? $level : 'info',
            'channel' => mb_substr($channel, 0, 40),
            'event' => mb_substr($event, 0, 80),
            'message' => mb_substr($message, 0, 5000),
            'context' => $this->redact($context),
            'correlation_id' => $this->correlationId(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }

        $safe = [];

        foreach (array_slice($context, 0, 20, true) as $key => $value) {
            $key = mb_substr((string) $key, 0, 80);

            if (preg_match('/password|secret|token|authorization|cookie|email|ip_address|body|destination|path|url/i', $key) === 1) {
                $safe[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->redact($value, $depth + 1);

                continue;
            }

            if (is_string($value)) {
                $safe[$key] = mb_substr($value, 0, 200);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function correlationId(): string
    {
        $candidate = request()->header('X-Request-ID');

        if (is_string($candidate) && preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::uuid();
    }
}
