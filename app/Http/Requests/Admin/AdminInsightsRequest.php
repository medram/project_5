<?php

namespace App\Http\Requests\Admin;

use App\Services\Admin\AdminInsightsRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminInsightsRequest extends FormRequest
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
        return [
            'range' => ['nullable', 'in:7,30,90,custom'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('range') !== 'custom') {
                    return;
                }

                if ($validator->errors()->has('from') || $validator->errors()->has('to')) {
                    return;
                }

                $from = $this->input('from');
                $to = $this->input('to');

                if (! is_string($from) || ! is_string($to)) {
                    $validator->errors()->add('from', 'Choose a UTC start and end date.');

                    return;
                }

                if ($from > $to) {
                    $validator->errors()->add('to', 'The end date must be on or after the start date.');

                    return;
                }

                $days = CarbonImmutable::parse($from, 'UTC')
                    ->startOfDay()
                    ->diffInDays(CarbonImmutable::parse($to, 'UTC')->startOfDay()) + 1;

                if ($days > 365) {
                    $validator->errors()->add('to', 'Insight ranges are capped at 365 days.');
                }
            },
        ];
    }

    public function range(): AdminInsightsRange
    {
        $preset = (string) ($this->validated()['range'] ?? '30');

        if ($preset === 'custom') {
            $from = CarbonImmutable::parse((string) $this->validated()['from'], 'UTC')->startOfDay();
            $to = CarbonImmutable::parse((string) $this->validated()['to'], 'UTC')->startOfDay();

            return new AdminInsightsRange(
                'custom',
                $from,
                $to->addDay(),
                (int) $from->diffInDays($to) + 1,
                $from->toDateString(),
                $to->toDateString(),
            );
        }

        $days = (int) $preset;
        $today = CarbonImmutable::now('UTC')->startOfDay();

        return new AdminInsightsRange(
            (string) $days,
            $today->subDays($days - 1),
            $today->addDay(),
            $days,
            $today->subDays($days - 1)->toDateString(),
            $today->toDateString(),
        );
    }
}