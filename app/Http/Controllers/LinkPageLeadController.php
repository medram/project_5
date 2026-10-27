<?php

namespace App\Http\Controllers;

use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\LinkPageLead;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LinkPageLeadController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function index(Request $request, LinkPage $linkPage): Response
    {
        $workspace = $this->authorizePage($request, $linkPage);
        $blocks = $this->formBlocks($linkPage);
        $fieldsByBlock = $this->fieldsByBlock($blocks);
        $leads = $this->leadQuery($workspace, $linkPage)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();
        $leads->through(fn (LinkPageLead $lead): array => $this->leadData($lead, $fieldsByBlock));

        return Inertia::render('link-pages/leads', [
            'linkPage' => [
                'id' => $linkPage->id,
                'name' => $linkPage->name,
                'slug' => $linkPage->slug,
            ],
            'backUrl' => route('link-pages.show', $linkPage),
            'exportUrl' => route('link-pages.leads.export', $linkPage),
            'columns' => $this->columns($blocks),
            'leads' => $leads,
        ]);
    }

    public function export(Request $request, LinkPage $linkPage): StreamedResponse
    {
        $workspace = $this->authorizePage($request, $linkPage);
        $blocks = $this->formBlocks($linkPage);
        $fieldsByBlock = $this->fieldsByBlock($blocks);
        $columns = $this->columns($blocks);
        $columnIndexes = [];
        foreach ($columns as $index => $column) {
            $columnIndexes[$column['key']] = $index + 3;
        }
        $query = $this->leadQuery($workspace, $linkPage)
            ->orderBy('created_at')
            ->orderBy('id');
        $headers = [
            'submission_id',
            'submitted_at_utc',
            'form_block_id',
            ...array_column($columns, 'key'),
        ];

        return response()->streamDownload(function () use ($query, $fieldsByBlock, $columnIndexes, $headers): void {
            $stream = fopen('php://output', 'wb');
            if (! is_resource($stream)) {
                return;
            }

            fputcsv($stream, array_map(fn (string $cell): string => $this->csvCell($cell), $headers), ',', '"', '');
            foreach ($query->cursor() as $lead) {
                $row = [
                    (string) $lead->id,
                    $lead->created_at->utc()->format(DATE_ATOM),
                    (string) $lead->link_page_block_id,
                    ...array_fill(0, count($headers) - 3, ''),
                ];
                $fieldIds = $fieldsByBlock[$lead->link_page_block_id] ?? [];
                foreach ($fieldIds as $fieldId) {
                    $key = "form_{$lead->link_page_block_id}_{$fieldId}";
                    $index = $columnIndexes[$key] ?? null;
                    $value = $lead->responses[$fieldId] ?? null;
                    if ($index !== null && is_string($value)) {
                        $row[$index] = $this->csvCell($value);
                    }
                }

                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, "link-page-leads-{$linkPage->slug}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizePage(Request $request, LinkPage $linkPage): Workspace
    {
        $workspace = $this->workspaces->currentFor($request->user());
        abort_unless((int) $linkPage->workspace_id === (int) $workspace->id, 404);
        Gate::authorize('viewLeads', $linkPage);

        return $workspace;
    }

    /** @return Collection<int, LinkPageBlock> */
    private function formBlocks(LinkPage $linkPage): Collection
    {
        return $linkPage->blocks()
            ->where('type', 'form')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /** @param Collection<int, LinkPageBlock> $blocks
     * @return array<int, list<string>>
     */
    private function fieldsByBlock(Collection $blocks): array
    {
        $fieldsByBlock = [];
        foreach ($blocks as $block) {
            $fieldsByBlock[$block->id] = array_column($this->fields($block), 'id');
        }

        return $fieldsByBlock;
    }

    /** @param Collection<int, LinkPageBlock> $blocks
     * @return list<array{key: string, fieldId: string, formBlockId: int, label: string}>
     */
    private function columns(Collection $blocks): array
    {
        $columns = [];
        foreach ($blocks as $block) {
            foreach ($this->fields($block) as $field) {
                $columns[] = [
                    'key' => "form_{$block->id}_{$field['id']}",
                    'fieldId' => $field['id'],
                    'formBlockId' => $block->id,
                    'label' => "Form {$block->id} — {$field['label']}",
                ];
            }
        }

        return $columns;
    }

    /** @param array<int, list<string>> $fieldsByBlock
     * @return array{id: int, submittedAt: string, formBlockId: int, values: array<string, string>}
     */
    private function leadData(LinkPageLead $lead, array $fieldsByBlock): array
    {
        $values = [];
        foreach ($fieldsByBlock[$lead->link_page_block_id] ?? [] as $fieldId) {
            $value = $lead->responses[$fieldId] ?? null;
            if (is_string($value)) {
                $values[$fieldId] = $value;
            }
        }

        return [
            'id' => $lead->id,
            'submittedAt' => $lead->created_at->utc()->format(DATE_ATOM),
            'formBlockId' => $lead->link_page_block_id,
            'values' => $values,
        ];
    }

    /** @return list<array{id: string, label: string, type: string, required: bool}> */
    private function fields(LinkPageBlock $block): array
    {
        $fields = $block->content['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields)) {
            return [];
        }

        $result = [];
        foreach ($fields as $field) {
            if (
                ! is_array($field)
                || ! is_string($field['id'] ?? null)
                || ! is_string($field['label'] ?? null)
                || ! is_string($field['type'] ?? null)
            ) {
                continue;
            }
            $result[] = [
                'id' => $field['id'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
            ];
        }

        return $result;
    }

    /** @return Builder<LinkPageLead> */
    private function leadQuery(Workspace $workspace, LinkPage $linkPage): Builder
    {
        return LinkPageLead::query()
            ->where('workspace_id', $workspace->id)
            ->where('link_page_id', $linkPage->id)
            ->where('retention_expires_at', '>', now('UTC'));
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\t\r\n ]*[=+\-@]/', $value) === 1 ? "'{$value}" : $value;
    }
}
