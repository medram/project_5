<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOperationLogController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        [$query, $filters] = $this->filtered($request);
        $rows = $query->orderByDesc('id')->paginate(50)->withQueryString()->through(fn (OperationLog $log): array => $this->row($log));

        return Inertia::render('admin/logs/index', [
            'rows' => $rows,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$query, $filters] = $this->filtered($request);

        return Response::streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }
            fputcsv($output, ['created_at', 'level', 'channel', 'event', 'message', 'correlation_id', 'context']);
            $query->orderBy('id')->chunk(500, function ($logs) use ($output): void {
                foreach ($logs as $log) {
                    fputcsv($output, array_map($this->csvCell(...), [
                        $log->created_at?->toISOString(),
                        $log->level,
                        $log->channel,
                        $log->event,
                        $log->message,
                        $log->correlation_id,
                        json_encode($log->context, JSON_UNESCAPED_SLASHES),
                    ]));
                }
            });
            fclose($output);
        }, 'operation-logs.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvCell(mixed $value): string
    {
        $cell = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell;
    }

    /**
     * @return array{0: Builder<OperationLog>, 1: array<string, mixed>}
     */
    private function filtered(Request $request): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'in:debug,info,warning,error,critical'],
            'channel' => ['nullable', 'string', 'max:40'],
        ]);
        $query = OperationLog::query();
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn ($logs) => $logs->where('message', 'like', $like)->orWhere('event', 'like', $like));
        }
        if (($filters['level'] ?? null) !== null) {
            $query->where('level', $filters['level']);
        }
        if (($filters['channel'] ?? null) !== null) {
            $query->where('channel', $filters['channel']);
        }

        return [$query, [
            'q' => $search,
            'level' => $filters['level'] ?? null,
            'channel' => $filters['channel'] ?? null,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(OperationLog $log): array
    {
        return [
            'id' => $log->id,
            'level' => $log->level,
            'channel' => $log->channel,
            'event' => $log->event,
            'message' => $log->message,
            'context' => $log->context,
            'correlationId' => $log->correlation_id,
            'createdAt' => $log->created_at?->toISOString(),
        ];
    }
}
