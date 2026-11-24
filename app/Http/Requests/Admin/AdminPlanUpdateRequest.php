<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanMetric;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AdminPlanUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'featured' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'limits' => ['required', 'array'],
        ];

        foreach (PlanMetric::cases() as $metric) {
            $max = $metric->isByteMetric() ? 9007199254740991 : 4294967295;
            $rules['limits.'.$metric->value] = ['present', 'nullable', 'integer', 'min:0', 'max:'.$max];
        }

        if ($this->isMethod('post')) {
            $rules['code'] = ['required', 'string', 'regex:/\A[a-z0-9_-]{2,40}\z/', Rule::notIn(['free', 'pro', 'business']), Rule::unique('plans', 'code')];
        }

        return $rules;
    }
}
