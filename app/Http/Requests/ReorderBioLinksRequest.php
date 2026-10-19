<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderBioLinksRequest extends FormRequest
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
            'link_ids' => ['present', 'array'],
            'link_ids.*' => ['integer', 'distinct', 'exists:bio_links,id'],
        ];
    }
}
