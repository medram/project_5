<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBioLinkRequest;
use App\Http\Requests\UpdateBioLinkRequest;
use App\Http\Middleware\ScopedAdminContent;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BioLinkController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function store(StoreBioLinkRequest $request, BioPage $bioPage): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        app(AdminContentAccess::class)->authorize($request, 'update', $bioPage);
        $position = max((int) ($bioPage->bioLinks()->max('position') ?? -1), (int) ($bioPage->contentBlocks()->max('position') ?? -1)) + 1;
        $bioPage->bioLinks()->create([...$request->validated(), 'position' => $position]);

        return redirect($request->attributes->get(ScopedAdminContent::ATTRIBUTE)['editor_url'] ?? route('bio-pages.show', $bioPage));
    }

    public function update(UpdateBioLinkRequest $request, BioPage $bioPage, BioLink $bioLink): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        app(AdminContentAccess::class)->authorize($request, 'update', $bioPage);
        $this->ensureBelongsToPage($bioPage, $bioLink);
        $bioLink->update($request->validated());

        return redirect($request->attributes->get(ScopedAdminContent::ATTRIBUTE)['editor_url'] ?? route('bio-pages.show', $bioPage));
    }

    public function destroy(Request $request, BioPage $bioPage, BioLink $bioLink): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        app(AdminContentAccess::class)->authorize($request, 'update', $bioPage);
        $this->ensureBelongsToPage($bioPage, $bioLink);
        $bioLink->delete();

        return redirect($request->attributes->get(ScopedAdminContent::ATTRIBUTE)['editor_url'] ?? route('bio-pages.show', $bioPage));
    }

    private function ensureBelongsToPage(BioPage $bioPage, BioLink $bioLink): void
    {
        abort_unless($bioLink->bio_page_id === $bioPage->id, 404);
    }

    private function ensureInCurrentWorkspace(Request $request, BioPage $bioPage): void
    {
        abort_unless((int) $bioPage->workspace_id === (int) $this->workspaces->forContentRequest($request)->id, 404);
    }
}
