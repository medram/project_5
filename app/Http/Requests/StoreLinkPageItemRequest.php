<?php

namespace App\Http\Requests;

use App\Services\Links\FirstPartyDestinationGuard;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Url;

class StoreLinkPageItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:100'],
            'destination_url' => [
                'required',
                'string',
                'max:2048',
                (new Url)->protocols(['http', 'https']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && app(FirstPartyDestinationGuard::class)->rejects($value)) {
                        $fail('The destination URL must not target this application.');
                    }
                },
            ],
            'is_active' => ['required', 'boolean'],
            'link_page_variant_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
