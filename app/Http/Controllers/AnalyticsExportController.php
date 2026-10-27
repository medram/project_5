<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyticsFilterRequest;
use App\Models\AnalyticsEvent;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsInsights;
use App\Services\Workspaces\WorkspaceContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsExportController extends Controller
{
    private const HEADERS = [
        'uuid',
        'event_name',
        'occurred_at',
        'link_id',
        'bio_page_id',
        'bio_link_id',
        'link_page_id',
        'link_page_variant_id',
        'link_page_item_id',
        'link_overlay_id',
        'country_code',
        'browser',
        'platform',
        'referrer_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

    public function __construct(
        private readonly AnalyticsInsights $analytics,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    public function __invoke(AnalyticsFilterRequest $request): StreamedResponse
    {
        return $this->forWorkspace(
            $this->workspaceContext->currentFor($request->user()),
            $request->filters(),
        );
    }

    /** @param array<string, mixed> $filters */
    public function forWorkspace(Workspace $workspace, array $filters): StreamedResponse
    {
        $events = $this->analytics->eventRowsForWorkspace($workspace, $filters)
            ->select(AnalyticsInsights::EVENT_ROW_COLUMNS)
            ->orderByDesc('occurred_at')
            ->orderByDesc('uuid');

        return response()->streamDownload(function () use ($events): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                return;
            }

            try {
                fputcsv($stream, self::HEADERS, ',', '"', '', "\r\n");

                foreach ($events->cursor() as $event) {
                    fputcsv($stream, $this->row($event), ',', '"', '', "\r\n");
                }
            } finally {
                fclose($stream);
            }
        }, 'analytics-events.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return array<int, string> */
    private function row(AnalyticsEvent $event): array
    {
        return array_map(fn (mixed $value): string => $this->safeCell($value), [
            $event->uuid,
            $event->event_name,
            $event->occurred_at?->toIso8601String(),
            $event->link_id,
            $event->bio_page_id,
            $event->bio_link_id,
            $event->link_page_id,
            $event->link_page_variant_id,
            $event->link_page_item_id,
            $event->link_overlay_id,
            $event->country_code,
            $event->browser,
            $event->platform,
            $event->referrer_host,
            $event->utm_source,
            $event->utm_medium,
            $event->utm_campaign,
            $event->utm_term,
            $event->utm_content,
        ]);
    }

    private function safeCell(mixed $value): string
    {
        $cell = $value === null ? '' : (string) $value;

        return preg_match('/^\s*[=+\-@]/u', $cell) === 1 ? "'{$cell}" : $cell;
    }
}
