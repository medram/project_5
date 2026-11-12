<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreLinkRequest;
use App\Models\Link;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Links\LinkAttributes;
use App\Services\Links\LinkRevisionService;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LinkCsvController extends Controller
{
    /** @var array<int, string> */
    private const COLUMNS = [
        'slug',
        'title',
        'destination_url',
        'delivery_mode',
        'link_page_id',
        'is_active',
        'folder_id',
        'custom_domain_id',
        'link_overlay_id',
        'available_from',
        'expires_at',
        'max_clicks',
        'approval_required',
        'utm_parameters',
        'custom_parameters',
        'routing_rules',
        'tag_ids',
    ];

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly EnforcesPlanLimits $planLimits,
        private readonly LinkAttributes $linkAttributes,
        private readonly LinkRevisionService $revisions,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'tag' => ['nullable', 'integer', Rule::exists('link_tags', 'id')->where('workspace_id', $workspace->id)],
            'status' => ['nullable', 'in:active,inactive,archived,pending'],
        ]);

        $links = $this->filteredLinks($workspace->links(), $filters, $workspace->id)
            ->with(['routingRules', 'tags'])
            ->orderBy('id');

        return response()->streamDownload(function () use ($links): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            fputcsv($stream, self::COLUMNS, ',', '"', '', "\r\n");
            $links->chunk(200, function ($page) use ($stream): void {
                foreach ($page as $link) {
                    fputcsv($stream, [
                        $link->slug,
                        $this->spreadsheetSafeTitle($link->title),
                        $link->destination_url,
                        $link->delivery_mode,
                        $link->link_page_id,
                        $link->is_active ? '1' : '0',
                        $link->folder_id,
                        $link->custom_domain_id,
                        $link->link_overlay_id,
                        $link->available_from?->toIso8601String(),
                        $link->expires_at?->toIso8601String(),
                        $link->max_clicks,
                        $link->approval_required ? '1' : '0',
                        json_encode($link->utm_parameters ?? [], JSON_THROW_ON_ERROR),
                        json_encode($link->custom_parameters ?? [], JSON_THROW_ON_ERROR),
                        json_encode($link->routingRules->map(fn ($rule): array => [
                            'rule_type' => $rule->rule_type,
                            'match_value' => $rule->match_value,
                            'target_url' => $rule->target_url,
                        ])->all(), JSON_THROW_ON_ERROR),
                        json_encode($link->tags->modelKeys(), JSON_THROW_ON_ERROR),
                    ], ',', '"', '', "\r\n");
                }
            });

            fclose($stream);
        }, 'linkpilot-links.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $listState = $this->listState($request, $workspace->id);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $path = $request->file('file')?->getRealPath();
        $stream = is_string($path) ? fopen($path, 'rb') : false;
        if ($stream === false) {
            return $this->results([[
                'row' => 1,
                'status' => 'error',
                'errors' => ['The uploaded CSV could not be read.'],
            ]], $listState);
        }

        $headers = fgetcsv($stream, null, ',', '"', '');
        if (! is_array($headers)) {
            fclose($stream);

            return $this->results([[
                'row' => 1,
                'status' => 'error',
                'errors' => ['The CSV must include a header row.'],
            ]], $listState);
        }

        $headers = array_map(static fn ($header): string => strtolower(trim((string) $header)), $headers);
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        }

        if (count($headers) !== count(array_unique($headers)) || array_diff($headers, self::COLUMNS) !== []) {
            fclose($stream);

            return $this->results([[
                'row' => 1,
                'status' => 'error',
                'errors' => ['The CSV header contains duplicate or unsupported columns.'],
            ]], $listState);
        }

        $results = [];
        $rowNumber = 1;
        while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rowNumber++;
            if ($rowNumber > 101) {
                $results[] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'errors' => ['A CSV import can contain at most 100 data rows.'],
                ];
                break;
            }

            if (count($values) !== count($headers)) {
                $results[] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'errors' => ['The number of values does not match the CSV header.'],
                ];

                continue;
            }

            $input = array_combine($headers, $values);
            if (! is_array($input)) {
                $results[] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'errors' => ['The CSV row could not be read.'],
                ];

                continue;
            }

            $prepared = $this->prepareRow($input);
            if (isset($prepared['error'])) {
                $results[] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'errors' => [$prepared['error']],
                ];

                continue;
            }

            $data = $prepared['data'];
            $rowRequest = StoreLinkRequest::createFrom($request);
            $rowRequest->replace($data);
            try {
                $validator = Validator::make($data, $rowRequest->rules());
                $failed = $validator->fails();
            } catch (AuthorizationException) {
                $results[] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'errors' => ['You are not allowed to create links with the supplied approval settings.'],
                ];

                continue;
            }
            if ($failed) {
                $results[] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'errors' => ['Validation failed for: '.implode(', ', array_keys($validator->errors()->messages())).'.'],
                ];

                continue;
            }

            try {
                $validated = $validator->validated();
                $tagIds = array_map('intval', $validated['tag_ids'] ?? []);
                $routingRules = $validated['routing_rules'] ?? [];
                $link = $this->planLimits->create(
                    $workspace,
                    PlanMetric::Links,
                    function ($lockedWorkspace) use ($validated, $tagIds, $routingRules, $request): Link {
                        $link = $lockedWorkspace->links()->create([
                            ...$this->linkAttributes->fromValidated($validated),
                            'user_id' => $request->user()->id,
                        ]);
                        $this->revisions->recordInitial($link, $tagIds, $routingRules, $request->user());

                        return $link;
                    },
                );
            } catch (ValidationException $exception) {
                $results[] = [
                    'row' => $rowNumber,
                    'status' => 'error',
                    'errors' => [$exception->errors()['plan'][0] ?? 'The workspace link quota was reached.'],
                ];

                continue;
            }

            $results[] = [
                'row' => $rowNumber,
                'status' => 'created',
                'slug' => $link->slug,
                'errors' => [],
            ];
        }

        fclose($stream);
        if ($results === []) {
            $results[] = [
                'row' => 2,
                'status' => 'error',
                'errors' => ['The CSV contains no data rows.'],
            ];
        }

        return $this->results($results, $listState);
    }

    /** @param array<string, mixed> $input @return array{data?: array<string, mixed>, error?: string} */
    private function prepareRow(array $input): array
    {
        $data = [];
        foreach (self::COLUMNS as $column) {
            if (! array_key_exists($column, $input)) {
                continue;
            }

            $value = $input[$column];
            if ($value === '') {
                $value = null;
            }
            if (in_array($column, ['utm_parameters', 'custom_parameters', 'routing_rules', 'tag_ids'], true) && $value !== null) {
                try {
                    $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    return ['error' => "Invalid JSON in {$column}."];
                }
                if (! is_array($value)) {
                    return ['error' => "Invalid JSON structure in {$column}."];
                }
            }
            $data[$column] = $value;
        }

        $data['delivery_mode'] = $data['delivery_mode'] ?? 'direct';
        $data['is_active'] = $data['is_active'] ?? true;
        $data['approval_required'] = $data['approval_required'] ?? false;
        $data['utm_parameters'] = $data['utm_parameters'] ?? [];
        $data['custom_parameters'] = $data['custom_parameters'] ?? [];
        $data['routing_rules'] = $data['routing_rules'] ?? [];
        $data['tag_ids'] = $data['tag_ids'] ?? [];
        if (is_string($data['slug'] ?? null)) {
            $data['slug'] = strtolower(trim($data['slug']));
        }
        if (! is_array($data['tag_ids']) || ! array_is_list($data['tag_ids'])) {
            return ['error' => 'tag_ids must be a JSON array.'];
        }
        if (! is_array($data['routing_rules']) || ! array_is_list($data['routing_rules'])) {
            return ['error' => 'routing_rules must be a JSON array.'];
        }
        foreach ($data['routing_rules'] as $index => $rule) {
            if (! is_array($rule) || ! is_string($rule['match_value'] ?? null)) {
                continue;
            }

            $data['routing_rules'][$index]['match_value'] = ($rule['rule_type'] ?? null) === 'country'
                ? strtoupper(trim($rule['match_value']))
                : strtolower(trim($rule['match_value']));
        }

        return ['data' => $data];
    }

    /** @param array<string, mixed> $filters */
    private function filteredLinks(HasMany $links, array $filters, int $workspaceId): HasMany
    {
        if (($filters['search'] ?? '') !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $links->where(fn (Builder $query): Builder => $query
                ->whereRaw("title LIKE ? ESCAPE '!' ", [$pattern])
                ->orWhereRaw("slug LIKE ? ESCAPE '!' ", [$pattern]));
        }
        if (($filters['folder'] ?? '') !== '') {
            $links->where('folder_id', $filters['folder']);
        }
        if (($filters['tag'] ?? '') !== '') {
            $links->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($filters['tag']));
        }

        match ($filters['status'] ?? '') {
            'archived' => $links->whereNotNull('archived_at'),
            'pending' => $links->whereNull('archived_at')->where(function (Builder $query) use ($workspaceId): void {
                $query->where('approval_status', 'pending')
                    ->orWhereHas('revisions', fn (Builder $revisions): Builder => $revisions
                        ->where('workspace_id', $workspaceId)
                        ->where('status', 'pending'));
            }),
            'active', 'inactive' => $links->whereNull('archived_at')->where('is_active', $filters['status'] === 'active'),
            default => null,
        };

        return $links;
    }

    private function spreadsheetSafeTitle(?string $title): ?string
    {
        if ($title === null || preg_match('/^[\x00-\x20]*[=+\-@]/', $title) !== 1) {
            return $title;
        }

        return "'".$title;
    }

    /** @return array<string, mixed> */
    private function listState(Request $request, int $workspaceId): array
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'integer', Rule::exists('folders', 'id')->where('workspace_id', $workspaceId)],
            'tag' => ['nullable', 'integer', Rule::exists('link_tags', 'id')->where('workspace_id', $workspaceId)],
            'status' => ['nullable', 'in:active,inactive,archived,pending'],
            'sort' => ['nullable', 'in:created_at,title,clicks_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:table,cards'],
        ]);

        return $validator->fails() ? [] : $validator->validated();
    }

    /** @param array<int, array<string, mixed>> $results @param array<string, mixed> $listState */
    private function results(array $results, array $listState): Response
    {
        return Inertia::render('links/import-results', [
            'results' => $results,
            'listState' => $listState,
            'summary' => [
                'created' => count(array_filter($results, fn (array $row): bool => $row['status'] === 'created')),
                'failed' => count(array_filter($results, fn (array $row): bool => $row['status'] === 'error')),
            ],
        ]);
    }
}
