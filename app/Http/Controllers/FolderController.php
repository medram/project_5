<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Folder;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FolderController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaceContext) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name,created_at,links_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:table,cards'],
        ]);
        $filters = [
            'search' => (string) ($filters['search'] ?? ''),
            'sort' => $filters['sort'] ?? 'created_at',
            'direction' => $filters['direction'] ?? 'desc',
            'page' => (string) ($filters['page'] ?? ''),
            'view' => $filters['view'] ?? 'cards',
        ];
        $folders = $workspace->folders()
            ->withCount([
                'links' => fn ($query) => $query->where('links.workspace_id', $workspace->id),
                'clicks' => fn ($query) => $query->where('links.workspace_id', $workspace->id),
            ])
            ->withCount(['analyticsEvents' => fn ($query) => $query->where('analytics_events.workspace_id', $workspace->id)]);

        if ($filters['search'] !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%';
            $folders->whereRaw("name LIKE ? ESCAPE '!' ", [$pattern]);
        }

        $folders->orderBy($filters['sort'], $filters['direction'])->orderBy('id');

        return Inertia::render('folders/index', [
            'folders' => $folders->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $workspace->folders()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return to_route('folders.index', $this->listState($request, resetPage: true));
    }

    public function update(UpdateFolderRequest $request, Folder $folder): RedirectResponse
    {
        $folder = $this->workspaceContext->currentFor($request->user())->folders()->findOrFail($folder->getKey());
        Gate::authorize('update', $folder);
        $folder->update($request->validated());

        return to_route('folders.index', $this->listState($request, resetPage: true));
    }

    public function destroy(Request $request, Folder $folder): RedirectResponse
    {
        $folder = $this->workspaceContext->currentFor($request->user())->folders()->findOrFail($folder->getKey());
        Gate::authorize('delete', $folder);
        $folder->delete();

        return to_route('folders.index', $this->listState($request, resetPage: true));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $workspace = $this->workspaceContext->currentFor($request->user());
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:20'],
            'ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('folders', 'id')->where('workspace_id', $workspace->id),
            ],
        ])['ids'];

        DB::transaction(function () use ($workspace, $ids): void {
            $folders = $workspace->folders()->whereKey($ids)->lockForUpdate()->get();
            abort_unless($folders->count() === count($ids), 404);

            foreach ($folders as $folder) {
                Gate::authorize('delete', $folder);
            }

            $folders->each(fn (Folder $folder) => $folder->delete());
        });

        return to_route('folders.index', $this->listState($request, resetPage: true));
    }

    /**
     * @return array<string, mixed>
     */
    private function listState(Request $request, bool $resetPage = false): array
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:name,created_at,links_count'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'view' => ['nullable', 'in:table,cards'],
        ]);

        if ($validator->fails()) {
            return [];
        }

        $state = $validator->validated();
        if ($resetPage) {
            unset($state['page']);
        }

        return $state;
    }
}
