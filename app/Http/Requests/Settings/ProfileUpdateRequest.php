<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Services\Accounts\AccountProfileOptions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        $country = $this->input('country_code');

        $this->merge([
            'locale' => is_string($this->input('locale')) ? mb_strtolower(trim($this->input('locale'))) ?: null : null,
            'country_code' => is_string($country) ? mb_strtoupper(trim($country)) ?: null : null,
            'timezone' => is_string($this->input('timezone')) ? trim($this->input('timezone')) ?: null : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $options = app(AccountProfileOptions::class);

        return [
            ...$this->profileRules($this->user()->id),
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', 'max:12', function (string $attribute, mixed $value, Closure $fail) use ($options): void {
                if ($value !== null && ! $options->isSupportedLocale($value)) {
                    $fail('Choose a published interface language.');
                }
            }],
            'country_code' => ['nullable', 'string', 'size:2', function (string $attribute, mixed $value, Closure $fail) use ($options): void {
                if ($value !== null && ! $options->isCountry($value)) {
                    $fail('Choose a valid ISO 3166-1 country.');
                }
            }],
            'timezone' => ['nullable', 'string', 'max:64', function (string $attribute, mixed $value, Closure $fail) use ($options): void {
                if ($value !== null && ! $options->isTimezone($value)) {
                    $fail('Choose a valid IANA timezone.');
                }
            }],
        ];
    }
}
