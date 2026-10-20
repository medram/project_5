<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkOverlayRequest;
use App\Models\LinkOverlay;
use App\Models\TrackingPixel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LinkOverlayController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('overlays/index', [
            'overlays' => $request->user()->linkOverlays()
                ->with(['trackingPixels:id'])
                ->withCount('links')
                ->latest()
                ->get()
                ->map(fn (LinkOverlay $overlay): array => $this->overlayData($overlay))
                ->values()
                ->all(),
            'availablePixels' => $request->user()->trackingPixels()
                ->orderBy('name')
                ->get()
                ->map(fn (TrackingPixel $pixel): array => $this->pixelData($pixel))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreLinkOverlayRequest $request): RedirectResponse
    {
        $attributes = $this->overlayAttributes($request);
        $pixelIds = $this->pixelIds($request);

        DB::transaction(function () use ($attributes, $pixelIds, $request): void {
            $overlay = $request->user()->linkOverlays()->create($attributes);
            $this->syncTrackingPixels($overlay, $request->user(), $pixelIds);
        });

        return to_route('overlays.index');
    }

    public function show(LinkOverlay $linkOverlay): Response
    {
        Gate::authorize('view', $linkOverlay);

        return Inertia::render('overlays/index', [
            'overlays' => [$this->overlayData($linkOverlay)],
            'availablePixels' => $linkOverlay->user->trackingPixels()
                ->orderBy('name')
                ->get()
                ->map(fn (TrackingPixel $pixel): array => $this->pixelData($pixel))
                ->values()
                ->all(),
        ]);
    }

    public function update(
        StoreLinkOverlayRequest $request,
        LinkOverlay $linkOverlay,
    ): RedirectResponse {
        Gate::authorize('update', $linkOverlay);

        $attributes = $this->overlayAttributes($request);
        $pixelIds = $this->pixelIds($request);

        DB::transaction(function () use ($attributes, $linkOverlay, $pixelIds, $request): void {
            $linkOverlay->update($attributes);
            $this->syncTrackingPixels($linkOverlay, $request->user(), $pixelIds);
        });

        return to_route('overlays.index');
    }

    public function destroy(LinkOverlay $linkOverlay): RedirectResponse
    {
        Gate::authorize('delete', $linkOverlay);

        $linkOverlay->delete();

        return to_route('overlays.index');
    }

    /**
     * @return array{name: string, heading: string, body: string|null, cta_label: string, is_active: bool}
     */
    private function overlayAttributes(StoreLinkOverlayRequest $request): array
    {
        $attributes = $request->validated();
        unset($attributes['tracking_pixel_ids']);

        return $attributes;
    }

    /**
     * @return array<int, int>
     */
    private function pixelIds(StoreLinkOverlayRequest $request): array
    {
        $pixelIds = $request->validated('tracking_pixel_ids', []);

        return is_array($pixelIds) ? array_map('intval', $pixelIds) : [];
    }

    /**
     * @param  array<int, int>  $pixelIds
     */
    private function syncTrackingPixels(LinkOverlay $overlay, User $user, array $pixelIds): void
    {
        if (count($pixelIds) > 5 || count($pixelIds) !== count(array_unique($pixelIds))) {
            throw ValidationException::withMessages([
                'tracking_pixel_ids' => 'An overlay can use up to five distinct active pixels.',
            ]);
        }

        $availablePixelIds = $user->trackingPixels()
            ->where('is_active', true)
            ->whereKey($pixelIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $providedPixelIds = $pixelIds;

        sort($availablePixelIds);
        sort($providedPixelIds);

        if ($availablePixelIds !== $providedPixelIds) {
            throw ValidationException::withMessages([
                'tracking_pixel_ids' => 'Select only active pixels owned by this account.',
            ]);
        }

        $overlay->trackingPixels()->sync($pixelIds);
    }

    /**
     * @return array{id: int, name: string, provider: string, providerId: string, isActive: bool}
     */
    private function pixelData(TrackingPixel $pixel): array
    {
        return [
            'id' => $pixel->id,
            'name' => $pixel->name,
            'provider' => $pixel->provider,
            'providerId' => $pixel->provider_id,
            'isActive' => $pixel->is_active,
        ];
    }

    /**
     * @return array{id: int, name: string, heading: string, body: string|null, ctaLabel: string, isActive: bool, linksCount: int, trackingPixelIds: array<int, int>}
     */
    private function overlayData(LinkOverlay $overlay): array
    {
        return [
            'id' => $overlay->id,
            'name' => $overlay->name,
            'heading' => $overlay->heading,
            'body' => $overlay->body,
            'ctaLabel' => $overlay->cta_label,
            'isActive' => $overlay->is_active,
            'linksCount' => $overlay->links_count ?? 0,
            'trackingPixelIds' => $overlay->trackingPixels
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all(),
        ];
    }
}
