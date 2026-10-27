<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiKeyRequest extends FormRequest
{
    public const AVAILABLE_ABILITIES = [
        'links:read' => 'Read links',
        'links:write' => 'Create, update, and delete links',
        'bio-pages:read' => 'Read bio pages',
        'bio-pages:write' => 'Create, update, and delete bio pages',
        'link-pages:read' => 'Read link pages',
        'link-pages:write' => 'Create, update, and delete link pages',
        'analytics:read' => 'Read analytics and exports',
        'webhooks:read' => 'Read webhooks',
        'webhooks:write' => 'Create, update, and delete webhooks',
        'events:write' => 'Ingest declared custom events',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'distinct', Rule::in(array_keys(self::AVAILABLE_ABILITIES))],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
