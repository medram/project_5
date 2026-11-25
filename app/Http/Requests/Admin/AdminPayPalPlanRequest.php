<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminPayPalPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => [$this->isMethod('delete') ? 'sometimes' : 'required', 'string', 'max:255'],
            'expected_current_plan_id' => ['present', 'nullable', 'string', 'max:255'],
        ];
    }
}
