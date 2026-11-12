<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreLinkPageRequest;
use App\Models\LinkPage;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Content\ContentMediaStorage;
use App\Services\LinkPages\LinkPagePresentation;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkPageController extends Controller
{
    public function __construct(
        private readonly EnforcesPlanLimits $planLimits,
        private readonly WorkspaceContext $workspaces,
        private readonly LinkPagePresentation $presentation,
        private readonly ContentMediaStorage $media,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaces->currentFor($request->user());

        return Inertia::render('link-pages/index', [
            'linkPages' => $workspace->linkPages()
                ->withCount('items')
                ->latest()
                ->get()
                ->map(fn (LinkPage $linkPage): array => $this->pageData($linkPage))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreLinkPageRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        $attributes = $request->validated();
        $linkPage = $this->planLimits->create(
            $workspace,
            PlanMetric::LinkPages,
            function (Workspace $workspace) use ($attributes, $request): LinkPage {
                $linkPage = $workspace->linkPages()->create([
                    ...$attributes,
                    'user_id' => $request->user()->id,
                ]);
                $linkPage->variants()->create([
                    'name' => 'Control',
                    'weight' => 100,
                    'is_control' => true,
                    'is_active' => true,
                ]);

                return $linkPage;
            },
        );

        return to_route('link-pages.show', $linkPage);
    }

    public function show(Request $request, LinkPage $linkPage): Response
    {
        $this->ensureInCurrentWorkspace($request, $linkPage);
        Gate::authorize('view', $linkPage);
        $controlVariant = $linkPage->controlVariant()->where('is_active', true)->firstOrFail();
        $requestedVariantId = filter_var($request->query('variant'), FILTER_VALIDATE_INT);
        $selectedVariant = is_int($requestedVariantId) && $requestedVariantId > 0
            ? $linkPage->variants()->whereKey($requestedVariantId)->first()
            : null;
        $selectedVariant ??= $controlVariant;
        $variants = $linkPage->variants()->orderByDesc('is_control')->orderBy('id')->get();
        $items = $this->presentation->make($linkPage, editor: true, variant: $selectedVariant);

        $canViewLeads = $request->user()->can('viewLeads', $linkPage);

        return Inertia::render('link-pages/edit', [
            'linkPage' => $this->pageData($linkPage),
            'variants' => $variants->map(fn ($variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'weight' => $variant->weight,
                'isControl' => $variant->is_control,
                'isActive' => $variant->is_active,
            ])->values()->all(),
            'selectedVariantId' => $selectedVariant->id,
            'insightsUrl' => route('link-pages.insights', $linkPage),
            'items' => $items,
            'previewItems' => array_values(array_filter(
                $items,
                fn (array $item): bool => ($item['isPreviewVisible'] ?? false) === true,
            )),
            'canViewLeads' => $canViewLeads,
            'leadsUrl' => $canViewLeads ? route('link-pages.leads.index', $linkPage) : null,
            'privacyUrl' => config('services.linkpilot.analytics.privacy_url'),
        ]);
    }

    public function update(StoreLinkPageRequest $request, LinkPage $linkPage): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $linkPage);
        Gate::authorize('update', $linkPage);
        $linkPage->update($request->validated());

        return to_route('link-pages.show', [
            'linkPage' => $linkPage,
            'variant' => $request->query('variant'),
        ]);
    }

    public function destroy(Request $request, LinkPage $linkPage): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $linkPage);
        Gate::authorize('delete', $linkPage);
        DB::transaction(function () use ($linkPage): void {
            foreach ($linkPage->blocks()->with('contentMedia')->get() as $block) {
                if ($block->contentMedia) {
                    $this->media->delete($block->contentMedia);
                }
            }
            $linkPage->delete();
        });

        return to_route('link-pages.index');
    }

    /** @return array<string, mixed> */
    private function pageData(LinkPage $linkPage): array
    {
        return [
            'id' => $linkPage->id,
            'name' => $linkPage->name,
            'slug' => $linkPage->slug,
            'title' => $linkPage->title,
            'description' => $linkPage->description,
            'template' => $linkPage->template,
            'isPublished' => $linkPage->is_published,
            'itemsCount' => $linkPage->items_count ?? 0,
            'publicUrl' => url("/pages/{$linkPage->slug}"),
        ];
    }

    private function ensureInCurrentWorkspace(Request $request, LinkPage $linkPage): void
    {
        $workspace = $this->workspaces->currentFor($request->user());
        abort_unless((int) $linkPage->workspace_id === (int) $workspace->id, 404);
    }
}
