<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLinkOverlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'heading', 'body', 'cta_label'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'heading' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:500'],
            'cta_label' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => [
                'sometimes',
                'nullable',
                'date',
                ...($this->filled('starts_at') ? ['after:starts_at'] : []),
            ],
            'display_delay_seconds' => ['sometimes', 'integer', 'between:0,60'],
        ];
    }
}
