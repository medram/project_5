<?php

namespace App\Http\Requests\Admin;

use App\Services\Billing\WorkspacePlanOverrides;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminWorkspaceOverrideRequest extends FormRequest
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
            'plan' => [
                'required',
                'string',
                Rule::exists('plans', 'code')->where('is_active', true),
            ],
            'source' => ['required', Rule::in(WorkspacePlanOverrides::SOURCES)],
            'reason' => ['required', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.exists' => 'Choose an active plan.',
            'source.in' => 'Choose an approved override source.',
            'expires_at.after_or_equal' => 'The override expiry must be in the future.',
        ];
    }
}
