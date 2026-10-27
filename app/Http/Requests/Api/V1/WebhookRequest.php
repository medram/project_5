<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Webhook;
use App\Services\Webhooks\WebhookEndpointValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['name', 'url'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if ($this->isMethod('POST') && ! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'url' => [
                $creating ? 'required' : 'sometimes',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && app(WebhookEndpointValidator::class)->validate($value) === null) {
                        $fail('Enter a public HTTPS endpoint without embedded credentials.');
                    }
                },
            ],
            'events' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'distinct', Rule::in(Webhook::supportedEvents())],
            'is_active' => ['sometimes', 'boolean'],
            'workspace_id' => ['prohibited'],
            'secret' => ['prohibited'],
        ];
    }
}
