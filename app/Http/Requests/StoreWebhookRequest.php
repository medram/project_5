<?php

namespace App\Http\Requests;

use App\Models\Webhook;
use App\Services\Webhooks\WebhookEndpointValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('url'))) {
            $this->merge(['url' => trim($this->input('url'))]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || app(WebhookEndpointValidator::class)->validate($value) === null) {
                        $fail('Enter a public HTTPS endpoint without embedded credentials.');
                    }
                },
            ],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(Webhook::supportedEvents())],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
