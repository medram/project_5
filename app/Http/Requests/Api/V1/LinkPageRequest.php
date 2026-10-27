<?php

namespace App\Http\Requests\Api\V1;

use App\Models\LinkPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkPageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'slug', 'title', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'slug' ? strtolower($value) : $value]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $slug = Rule::unique(LinkPage::class, 'slug');
        if ($this->route('linkPage') !== null) {
            $slug->ignore($this->route('linkPage'));
        }

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'slug' => [$creating ? 'required' : 'sometimes', 'string', 'max:32', 'regex:/\A[a-z0-9-]{3,32}\z/', $slug],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'template' => ['sometimes', 'required', Rule::in(['standard', 'profile', 'campaign'])],
            'is_published' => [$creating ? 'required' : 'sometimes', 'boolean'],
            'workspace_id' => ['prohibited'],
        ];
    }
}
