<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:40'],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function search(): string
    {
        return trim((string) ($this->validated()['q'] ?? ''));
    }

    public function state(): ?string
    {
        return $this->validated()['state'] ?? null;
    }

    public function sort(): string
    {
        return (string) ($this->validated()['sort'] ?? 'updated_at');
    }

    public function direction(): string
    {
        return (string) ($this->validated()['direction'] ?? 'desc');
    }
}
