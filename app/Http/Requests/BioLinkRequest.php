<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Url;

abstract class BioLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function bioLinkRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'destination_url' => [
                'required',
                'string',
                'max:2048',
                (new Url)->protocols(['http', 'https']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && $this->isApplicationOrigin($value)) {
                        $fail('The destination URL must not target this application.');
                    }
                },
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function isApplicationOrigin(string $url): bool
    {
        $destinationOrigin = $this->originFor($url);
        $applicationOrigin = $this->originFor((string) config('app.url'));

        return $destinationOrigin !== null && $destinationOrigin === $applicationOrigin;
    }

    private function originFor(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };

        return $port === null ? null : "{$scheme}://{$host}:{$port}";
    }
}
