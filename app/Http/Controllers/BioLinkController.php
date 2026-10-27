<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBioLinkRequest;
use App\Http\Requests\UpdateBioLinkRequest;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BioLinkController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function store(StoreBioLinkRequest $request, BioPage $bioPage): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        Gate::authorize('update', $bioPage);
        $position = max((int) ($bioPage->bioLinks()->max('position') ?? -1), (int) ($bioPage->contentBlocks()->max('position') ?? -1)) + 1;
        $bioPage->bioLinks()->create([...$request->validated(), 'position' => $position]);

        return to_route('bio-pages.show', $bioPage);
    }

    public function update(UpdateBioLinkRequest $request, BioPage $bioPage, BioLink $bioLink): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        Gate::authorize('update', $bioPage);
        $this->ensureBelongsToPage($bioPage, $bioLink);
        $bioLink->update($request->validated());

        return to_route('bio-pages.show', $bioPage);
    }

    public function destroy(Request $request, BioPage $bioPage, BioLink $bioLink): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        Gate::authorize('update', $bioPage);
        $this->ensureBelongsToPage($bioPage, $bioLink);
        $bioLink->delete();

        return to_route('bio-pages.show', $bioPage);
    }

    private function ensureBelongsToPage(BioPage $bioPage, BioLink $bioLink): void
    {
        abort_unless($bioLink->bio_page_id === $bioPage->id, 404);
    }

    private function ensureInCurrentWorkspace(Request $request, BioPage $bioPage): void
    {
        abort_unless((int) $bioPage->workspace_id === (int) $this->workspaces->currentFor($request->user())->id, 404);
    }
}
