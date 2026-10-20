<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrackingPixelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $provider = is_string($this->input('provider'))
            ? strtolower(trim($this->input('provider')))
            : $this->input('provider');
        $providerId = is_string($this->input('provider_id'))
            ? trim($this->input('provider_id'))
            : $this->input('provider_id');

        if ($provider === 'google_analytics' && is_string($providerId)) {
            $providerId = strtoupper($providerId);
        }

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', 'string', Rule::in(['meta_pixel', 'google_analytics'])],
            'provider_id' => [
                'required',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! $this->validProviderId($value)) {
                        $fail('Enter a valid identifier for the selected pixel provider.');
                    }
                },
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function validProviderId(string $providerId): bool
    {
        return match ($this->input('provider')) {
            'meta_pixel' => preg_match('/\A\d{5,20}\z/', $providerId) === 1,
            'google_analytics' => preg_match('/\AG-[A-Z0-9]{6,20}\z/', $providerId) === 1,
            default => false,
        };
    }
}
