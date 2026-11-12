<?php

namespace App\Services\Admin;

use Carbon\CarbonImmutable;

final readonly class AdminInsightsRange
{
    public function __construct(
        public string $preset,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public int $days,
        public string $from,
        public string $to,
    ) {}

    /**
     * @return array{preset: string, from: string, to: string, days: int, label: string}
     */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'from' => $this->from,
            'to' => $this->to,
            'days' => $this->days,
            'label' => $this->preset === 'custom'
                ? "{$this->from} to {$this->to}"
                : "Last {$this->days} days",
        ];
    }
}