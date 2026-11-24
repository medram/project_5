<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkOverlayRequest;
use App\Models\LinkOverlay;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkOverlayController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaces->currentFor($request->user());
        $canManageIntegrations = $request->user()->can('manageIntegrations', $workspace);
        Gate::authorize('view', $workspace);

        return Inertia::render('overlays/index', [
            'canManageIntegrations' => $canManageIntegrations,
            'overlays' => $workspace->linkOverlays()->withCount('links')->latest()->get()
                ->map(fn (LinkOverlay $overlay): array => $this->overlayData($overlay))->values()->all(),
        ]);
    }

    public function store(StoreLinkOverlayRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);
        $workspace->linkOverlays()->create([...$request->validated(), 'user_id' => $request->user()->id]);

        return to_route('overlays.index');
    }

    public function show(Request $request, LinkOverlay $linkOverlay): Response
    {
        $this->ensureInCurrentWorkspace($request, $linkOverlay);
        app(AdminContentAccess::class)->authorize($request, 'view', $linkOverlay);

        return Inertia::render('overlays/index', [
            'canManageIntegrations' => $request->user()->can('manageIntegrations', $linkOverlay->workspace),
            'overlays' => [$this->overlayData($linkOverlay)],
        ]);
    }

    public function update(StoreLinkOverlayRequest $request, LinkOverlay $linkOverlay): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $linkOverlay);
        app(AdminContentAccess::class)->authorize($request, 'update', $linkOverlay);
        $linkOverlay->update($request->validated());

        return to_route('overlays.index');
    }

    public function destroy(Request $request, LinkOverlay $linkOverlay): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $linkOverlay);
        Gate::authorize('delete', $linkOverlay);
        $linkOverlay->delete();

        return to_route('overlays.index');
    }

    private function overlayData(LinkOverlay $overlay): array
    {
        return [
            'id' => $overlay->id,
            'name' => $overlay->name,
            'heading' => $overlay->heading,
            'body' => $overlay->body,
            'ctaLabel' => $overlay->cta_label,
            'isActive' => $overlay->is_active,
            'startsAt' => $overlay->starts_at?->toIso8601String(),
            'expiresAt' => $overlay->expires_at?->toIso8601String(),
            'displayDelaySeconds' => (int) $overlay->display_delay_seconds,
            'linksCount' => $overlay->links_count ?? 0,
        ];
    }

    private function ensureInCurrentWorkspace(Request $request, LinkOverlay $overlay): void
    {
        abort_unless(
            (int) $overlay->workspace_id === (int) $this->workspaces->forContentRequest($request)->id,
            404,
        );
    }
}
