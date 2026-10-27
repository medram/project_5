<?php

namespace App\Http\Requests;

use App\Services\Links\FirstPartyDestinationGuard;
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
                    if (is_string($value) && app(FirstPartyDestinationGuard::class)->rejects($value)) {
                        $fail('The destination URL must not target this application.');
                    }
                },
            ],
            'is_pinned' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'embed_url' => ['prohibited'],
            'image_url' => ['prohibited'],
            'content_media_id' => ['prohibited'],
            'media_id' => ['prohibited'],
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
        ];
    }
}
