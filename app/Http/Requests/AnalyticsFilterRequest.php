<?php

namespace App\Http\Requests;

use App\Enums\AnalyticsWindow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsFilterRequest extends FormRequest
{
    private const RESOURCE_TYPES = [
        'link',
        'bio_page',
        'bio_link',
        'link_page',
        'link_page_variant',
        'link_page_item',
        'link_overlay',
    ];

    protected function prepareForValidation(): void
    {
        $country = $this->query('country');

        if (is_string($country)) {
            $this->merge(['country' => strtoupper(trim($country))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'integer', Rule::enum(AnalyticsWindow::class)],
            'resource_type' => ['nullable', 'required_with:resource_id', Rule::in(self::RESOURCE_TYPES)],
            'resource_id' => ['nullable', 'required_with:resource_type', 'integer', 'min:1'],
            'event_name' => ['nullable', 'string', 'max:64'],
            'browser' => ['nullable', 'string', 'max:32'],
            'platform' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'utm_source' => ['nullable', 'string', 'max:512'],
            'utm_medium' => ['nullable', 'string', 'max:512'],
            'utm_campaign' => ['nullable', 'string', 'max:512'],
            'utm_term' => ['nullable', 'string', 'max:512'],
            'utm_content' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array{
     *     days: int,
     *     resource_type: ?string,
     *     resource_id: ?int,
     *     event_name: ?string,
     *     browser: ?string,
     *     platform: ?string,
     *     country: ?string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     utm_term: ?string,
     *     utm_content: ?string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'days' => (int) ($validated['days'] ?? 30),
            'resource_type' => $validated['resource_type'] ?? null,
            'resource_id' => isset($validated['resource_id']) ? (int) $validated['resource_id'] : null,
            'event_name' => $validated['event_name'] ?? null,
            'browser' => $validated['browser'] ?? null,
            'platform' => $validated['platform'] ?? null,
            'country' => $validated['country'] ?? null,
            'utm_source' => $validated['utm_source'] ?? null,
            'utm_medium' => $validated['utm_medium'] ?? null,
            'utm_campaign' => $validated['utm_campaign'] ?? null,
            'utm_term' => $validated['utm_term'] ?? null,
            'utm_content' => $validated['utm_content'] ?? null,
        ];
    }
}
