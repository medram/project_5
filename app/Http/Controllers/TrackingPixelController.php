<?php

namespace App\Http\Controllers;

use App\Enums\PlanMetric;
use App\Http\Requests\StoreTrackingPixelRequest;
use App\Http\Requests\StoreTrackingPixelRuleRequest;
use App\Models\BioPage;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\TrackingPixel;
use App\Models\TrackingPixelRule;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrackingPixelController extends Controller
{
    public function __construct(
        private readonly EnforcesPlanLimits $planLimits,
        private readonly WorkspaceContext $workspaces,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaces->currentFor($request->user());
        $canManageIntegrations = $request->user()->can('manageIntegrations', $workspace);
        Gate::authorize('view', $workspace);

        return Inertia::render('tracking-pixels/index', [
            'canManageIntegrations' => $canManageIntegrations,
            'pixels' => $workspace->trackingPixels()->with('rules')->latest()->get()
                ->map(fn (TrackingPixel $pixel): array => $this->pixelData($pixel))->values()->all(),
            'availableTargets' => [
                ...$workspace->bioPages()->orderBy('display_name')->get(['id', 'display_name'])
                    ->map(fn (BioPage $page): array => [
                        'type' => TrackingPixelRule::TARGET_BIO_PAGE,
                        'id' => $page->id,
                        'name' => $page->display_name,
                    ])->all(),
                ...$workspace->linkPages()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (LinkPage $page): array => [
                        'type' => TrackingPixelRule::TARGET_LINK_PAGE,
                        'id' => $page->id,
                        'name' => $page->name,
                    ])->all(),
                ...$workspace->linkOverlays()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (LinkOverlay $overlay): array => [
                        'type' => TrackingPixelRule::TARGET_LINK_OVERLAY,
                        'id' => $overlay->id,
                        'name' => $overlay->name,
                    ])->all(),
            ],
        ]);
    }

    public function storeRule(StoreTrackingPixelRuleRequest $request, TrackingPixel $trackingPixel): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $trackingPixel);
        Gate::authorize('update', $trackingPixel);

        if (! $trackingPixel->is_active) {
            throw ValidationException::withMessages([
                'tracking_pixel' => 'Only active pixels can be assigned to a surface.',
            ]);
        }

        $workspace = $this->workspaces->currentFor($request->user());
        $validated = $request->validated();
        $target = match ($validated['target_type']) {
            'bio_page' => $workspace->bioPages()->find($validated['target_id']),
            'link_page' => $workspace->linkPages()->find($validated['target_id']),
            'link_overlay' => $workspace->linkOverlays()->find($validated['target_id']),
        };

        abort_if($target === null, 404);

        DB::transaction(function () use ($target, $trackingPixel, $workspace, $validated): void {
            $target->newQuery()
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($validated['target_type'], [
                TrackingPixelRule::TARGET_LINK_PAGE,
                TrackingPixelRule::TARGET_LINK_OVERLAY,
            ], true)) {
                $activeAssignments = TrackingPixelRule::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('target_type', $validated['target_type'])
                    ->where('target_id', $target->id)
                    ->whereHas('trackingPixel', fn ($query) => $query->where('is_active', true));

                $alreadyAssigned = (clone $activeAssignments)
                    ->where('tracking_pixel_id', $trackingPixel->id)
                    ->exists();

                if (! $alreadyAssigned && $activeAssignments->distinct()->count('tracking_pixel_id') >= 5) {
                    throw ValidationException::withMessages([
                        'tracking_pixel' => 'A page or overlay can use up to five distinct active pixels.',
                    ]);
                }
            }

            $trackingPixel->rules()->firstOrCreate([
                'workspace_id' => $workspace->id,
                'target_type' => $validated['target_type'],
                'target_id' => $target->id,
                'trigger' => $validated['trigger'],
            ]);
        });

        return to_route('tracking-pixels.index');
    }

    public function destroyRule(
        Request $request,
        TrackingPixel $trackingPixel,
        TrackingPixelRule $trackingPixelRule,
    ): RedirectResponse {
        $workspace = $this->workspaces->currentFor($request->user());
        abort_unless((int) $trackingPixel->workspace_id === (int) $workspace->id, 404);
        abort_unless(
            (int) $trackingPixelRule->workspace_id === (int) $workspace->id
            && (int) $trackingPixelRule->tracking_pixel_id === (int) $trackingPixel->id,
            404,
        );
        Gate::authorize('update', $trackingPixel);
        $trackingPixelRule->delete();

        return to_route('tracking-pixels.index');
    }

    public function store(StoreTrackingPixelRequest $request): RedirectResponse
    {
        $workspace = $this->workspaces->currentFor($request->user());
        Gate::authorize('manageIntegrations', $workspace);
        $this->planLimits->create($workspace, PlanMetric::Pixels, fn ($workspace): TrackingPixel => $workspace->trackingPixels()->create([...$request->validated(), 'user_id' => $request->user()->id])
        );

        return to_route('tracking-pixels.index');
    }

    public function update(StoreTrackingPixelRequest $request, TrackingPixel $trackingPixel): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $trackingPixel);
        Gate::authorize('update', $trackingPixel);
        $trackingPixel->update($request->validated());

        return to_route('tracking-pixels.index');
    }

    public function destroy(Request $request, TrackingPixel $trackingPixel): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $trackingPixel);
        Gate::authorize('delete', $trackingPixel);
        $trackingPixel->delete();

        return to_route('tracking-pixels.index');
    }

    private function pixelData(TrackingPixel $pixel): array
    {
        return [
            'id' => $pixel->id,
            'name' => $pixel->name,
            'provider' => $pixel->provider,
            'providerId' => $pixel->provider_id,
            'isActive' => $pixel->is_active,
            'rules' => $pixel->rules->map(fn (TrackingPixelRule $rule): array => [
                'id' => $rule->id,
                'targetType' => $rule->target_type,
                'targetId' => $rule->target_id,
                'trigger' => $rule->trigger,
            ])->values()->all(),
        ];
    }

    private function ensureInCurrentWorkspace(Request $request, TrackingPixel $pixel): void
    {
        abort_unless(
            (int) $pixel->workspace_id === (int) $this->workspaces->currentFor($request->user())->id,
            404,
        );
    }
}
