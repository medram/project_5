<?php

namespace App\Http\Requests;

use App\Models\Link;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Url;

abstract class LinkRequest extends FormRequest
{
    private const RESERVED_SLUGS = [
        'dashboard',
        'login',
        'register',
        'logout',
        'bio',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $slug = strtolower(trim($this->input('slug')));

            $this->merge([
                'slug' => $slug === '' ? null : $slug,
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function linkRules(
        ?Link $ignoredLink = null,
        bool $slugRequired = false,
    ): array {
        $uniqueSlug = Rule::unique(Link::class, 'slug');

        if ($ignoredLink !== null) {
            $uniqueSlug->ignore($ignoredLink);
        }

        return [
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
            'title' => ['nullable', 'string', 'max:160'],
            'slug' => [
                $slugRequired ? 'required' : 'nullable',
                'string',
                'max:32',
                'regex:/\A[a-z0-9-]{4,32}\z/',
                Rule::notIn(self::RESERVED_SLUGS),
                $uniqueSlug,
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
