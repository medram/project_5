<?php

namespace App\Http\Requests\Api\V1;

use App\Models\BioPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BioPageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('handle'))) {
            $this->merge(['handle' => strtolower(trim($this->input('handle')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $handle = Rule::unique(BioPage::class, 'handle');
        if ($this->route('bioPage') !== null) {
            $handle->ignore($this->route('bioPage'));
        }

        return [
            'handle' => [$creating ? 'required' : 'sometimes', 'string', 'max:32', 'regex:/\A[a-z0-9-]{3,32}\z/', $handle],
            'display_name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:280'],
            'is_published' => [$creating ? 'required' : 'sometimes', 'boolean'],
            'appearance' => ['sometimes', 'array:theme,typography,color,background'],
            'appearance.theme' => ['sometimes', Rule::in(['light', 'dark', 'soft'])],
            'appearance.typography' => ['sometimes', Rule::in(['sans', 'serif'])],
            'appearance.color' => ['sometimes', Rule::in(['blue', 'violet', 'green', 'amber', 'rose'])],
            'appearance.background' => ['sometimes', Rule::in(['plain', 'sand', 'lavender', 'midnight'])],
            'workspace_id' => ['prohibited'],
            'content_media_id' => ['prohibited'],
            'media_id' => ['prohibited'],
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
            '*.embed_url' => ['prohibited'],
            '*.image_url' => ['prohibited'],
        ];
    }
}
