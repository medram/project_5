<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrackingPixelRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', Rule::in(['bio_page', 'link_page', 'link_overlay'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'trigger' => [
                'required',
                'string',
                Rule::in($this->input('target_type') === 'link_page'
                    ? ['page_view', 'button_click', 'lead_submit']
                    : ['page_view', 'button_click']),
            ],
            'remote_script' => ['prohibited'],
            'script' => ['prohibited'],
            'script_url' => ['prohibited'],
        ];
    }
}
