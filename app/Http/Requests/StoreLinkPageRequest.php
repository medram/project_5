<?php

namespace App\Http\Requests;

use App\Models\LinkPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLinkPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'slug' => is_string($this->input('slug')) ? strtolower(trim($this->input('slug'))) : $this->input('slug'),
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $uniqueSlug = Rule::unique(LinkPage::class, 'slug');
        $linkPage = $this->route('linkPage');

        if ($linkPage instanceof LinkPage) {
            $uniqueSlug->ignore($linkPage);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:32',
                'regex:/\A[a-z0-9-]{3,32}\z/',
                $uniqueSlug,
            ],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'template' => ['sometimes', 'required', Rule::in(['standard', 'profile', 'campaign'])],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
