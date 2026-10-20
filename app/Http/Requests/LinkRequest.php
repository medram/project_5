<?php

namespace App\Http\Requests;

use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
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
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists(Folder::class, 'id')
                    ->where('user_id', $this->user()->id),
            ],
            'custom_domain_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $domain = CustomDomain::query()->find($value);

                    if ($domain === null || $domain->user_id !== $this->user()->id) {
                        $fail('The selected custom domain is unavailable.');

                        return;
                    }

                    $link = $this->route('link');

                    if ($link instanceof Link && $link->custom_domain_id === $domain->id) {
                        return;
                    }

                    if ($domain->status !== 'active') {
                        $fail('Only an active custom domain can be assigned to a link.');
                    }
                },
            ],
            'access_code' => ['nullable', 'string', 'max:255'],
            'clear_access_code' => ['nullable', 'boolean'],
            'link_overlay_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $overlay = LinkOverlay::query()->find($value);

                    if ($overlay === null || $overlay->user_id !== $this->user()->id) {
                        $fail('The selected overlay is unavailable.');

                        return;
                    }

                    $link = $this->route('link');

                    if ($link instanceof Link && $link->link_overlay_id === $overlay->id) {
                        return;
                    }

                    if (! $overlay->is_active) {
                        $fail('Only an active overlay can be assigned to a link.');
                    }
                },
            ],
            'available_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:available_from'],
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
