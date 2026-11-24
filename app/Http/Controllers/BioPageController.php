<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreBioPageRequest;
use App\Http\Requests\UpdateBioPageRequest;
use App\Http\Middleware\ScopedAdminContent;
use App\Models\BioPage;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\BioPages\BioPagePresentation;
use App\Services\Platform\AdminContentAccess;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BioPageController extends Controller
{
    public function __construct(
        private readonly WorkspaceContext $workspaces,
        private readonly EnforcesPlanLimits $planLimits,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaces->currentFor($request->user());

        return Inertia::render('bio-pages/index', ['bioPages' => $workspace->bioPages()->latest()->get()]);
    }

    public function store(StoreBioPageRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        $bioPage = $this->planLimits->create(
            $workspace,
            PlanMetric::BioPages,
            fn (): BioPage => $workspace->bioPages()->create([
                ...$request->validated(),
                'user_id' => $request->user()->id,
            ]),
        );

        return to_route('bio-pages.show', $bioPage);
    }

    public function show(Request $request, BioPage $bioPage): Response
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        app(AdminContentAccess::class)->authorize($request, 'view', $bioPage);
        $admin = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);
        $presentation = app(BioPagePresentation::class)->make(
            $bioPage,
            true,
            $admin ? fn ($page, $block) => route('admin.content.biolinks.blocks.preview-image', [$page, $block]) : null,
        );
        $bioPage->setAttribute('appearance', $presentation['appearance']);
        $previewAt = now('UTC')->toISOString();

        return Inertia::render($admin ? 'admin/content/bio-edit' : 'bio-pages/edit', [
            'bioPage' => $bioPage,
            'bioLinks' => $bioPage->bioLinks()->get(),
            'contentBlocks' => array_values(array_filter($presentation['items'], fn (array $item): bool => $item['type'] !== 'link')),
            'previewAt' => $previewAt,
            ...($admin ? ['backUrl' => $admin['list_url'], 'editorUrl' => $admin['editor_url']] : []),
        ]);
    }

    public function update(UpdateBioPageRequest $request, BioPage $bioPage): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        app(AdminContentAccess::class)->authorize($request, 'update', $bioPage);
        $data = $request->validated();

        if (isset($data['appearance'])) {
            $data['appearance'] = array_replace(
                $bioPage->appearance ?? [],
                $data['appearance'],
            );
        }

        $bioPage->update($data);

        $admin = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);

        return $admin ? redirect($admin['editor_url']) : back(fallback: route('bio-pages.show', $bioPage));
    }

    public function destroy(Request $request, BioPage $bioPage): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $bioPage);
        Gate::authorize('delete', $bioPage);
        $bioPage->delete();

        return to_route('bio-pages.index');
    }

    private function ensureInCurrentWorkspace(Request $request, BioPage $bioPage): void
    {
        abort_unless((int) $bioPage->workspace_id === (int) $this->workspaces->forContentRequest($request)->id, 404);
    }
}
