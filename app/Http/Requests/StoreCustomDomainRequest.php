<?php

namespace App\Http\Requests;

use App\Models\CustomDomain;
use App\Services\Domains\PublicLinkHostResolver;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('hostname'))) {
            $hostname = CustomDomain::normalizeHostname($this->input('hostname'));

            $this->merge([
                'hostname' => $hostname ?? trim($this->input('hostname')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'hostname' => [
                'required',
                'string',
                'max:253',
                Rule::unique(CustomDomain::class, 'hostname'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! $this->isAllowedHostname($value)) {
                        $fail('Enter a publicly routable hostname, without a scheme, path, or port.');
                    }
                },
            ],
        ];

    }

    private function isAllowedHostname(string $hostname): bool
    {
        if (CustomDomain::normalizeHostname($hostname) !== $hostname) {
            return false;
        }

        if (
            filter_var($hostname, FILTER_VALIDATE_IP) !== false
            || $hostname === 'localhost'
            || str_ends_with($hostname, '.localhost')
            || app(PublicLinkHostResolver::class)->isDefaultHost($hostname)
        ) {
            return false;
        }

        $labels = explode('.', $hostname);

        if (count($labels) < 2) {
            return false;
        }

        foreach ($labels as $label) {
            if (
                $label === ''
                || strlen($label) > 63
                || str_starts_with($label, '-')
                || str_ends_with($label, '-')
            ) {
                return false;
            }
        }

        return true;
    }
}
