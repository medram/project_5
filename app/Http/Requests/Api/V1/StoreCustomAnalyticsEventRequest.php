<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCustomAnalyticsEventRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'event_name' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! AnalyticsTracker::isValidCustomEventName($value)) {
                        $fail('Choose a valid declared custom event name.');
                    }
                },
            ],
            'resource_type' => ['required', 'in:link,bio_page,link_page'],
            'resource_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['event_name', 'resource_type', 'resource_id'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'Unsupported event field.');
            }
        });
    }
}
