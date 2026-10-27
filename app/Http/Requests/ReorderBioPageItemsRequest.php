<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderBioPageItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*' => ['required', 'array:type,id'],
            'items.*.type' => ['required', Rule::in(['link', 'block'])],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ];
    }
}
