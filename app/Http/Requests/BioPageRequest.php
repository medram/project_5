<?php

namespace App\Http\Requests;

use App\Models\BioPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class BioPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('handle'))) {
            $this->merge([
                'handle' => strtolower(trim($this->input('handle'))),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function bioPageRules(?BioPage $ignoredBioPage = null): array
    {
        $uniqueHandle = Rule::unique(BioPage::class, 'handle');

        if ($ignoredBioPage !== null) {
            $uniqueHandle->ignore($ignoredBioPage);
        }

        return [
            'handle' => [
                'required',
                'string',
                'max:32',
                'regex:/\A[a-z0-9-]{3,32}\z/',
                $uniqueHandle,
            ],
            'display_name' => ['required', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:280'],
            'is_published' => ['required', 'boolean'],
            'appearance' => ['sometimes', 'array:theme,typography,color,background'],
            'appearance.theme' => ['sometimes', Rule::in(['light', 'dark', 'soft'])],
            'appearance.typography' => ['sometimes', Rule::in(['sans', 'serif'])],
            'appearance.color' => ['sometimes', Rule::in(['blue', 'violet', 'green', 'amber', 'rose'])],
            'appearance.background' => ['sometimes', Rule::in(['plain', 'sand', 'lavender', 'midnight'])],
            'embed_url' => ['prohibited'],
            'image_url' => ['prohibited'],
            'content_media_id' => ['prohibited'],
            'media_id' => ['prohibited'],
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
            '*.embed_url' => ['prohibited'],
            '*.image_url' => ['prohibited'],
        ];
    }
}
