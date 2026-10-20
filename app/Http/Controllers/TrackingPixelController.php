<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTrackingPixelRequest;
use App\Models\TrackingPixel;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrackingPixelController extends Controller
{
    public function __construct(private readonly EnforcesPlanLimits $planLimits) {}

    public function index(Request $request): Response
    {
        return Inertia::render('tracking-pixels/index', [
            'pixels' => $request->user()->trackingPixels()
                ->withCount('linkPages')
                ->latest()
                ->get()
                ->map(fn (TrackingPixel $pixel): array => $this->pixelData($pixel))
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreTrackingPixelRequest $request): RedirectResponse
    {
        $this->planLimits->create(
            $request->user(),
            'pixels',
            fn ($user): TrackingPixel => $user->trackingPixels()->create($request->validated()),
        );

        return to_route('tracking-pixels.index');
    }

    public function update(
        StoreTrackingPixelRequest $request,
        TrackingPixel $trackingPixel,
    ): RedirectResponse {
        Gate::authorize('update', $trackingPixel);

        $trackingPixel->update($request->validated());

        return to_route('tracking-pixels.index');
    }

    public function destroy(TrackingPixel $trackingPixel): RedirectResponse
    {
        Gate::authorize('delete', $trackingPixel);

        $trackingPixel->delete();

        return to_route('tracking-pixels.index');
    }

    /**
     * @return array{id: int, name: string, provider: string, providerId: string, isActive: bool, linkPagesCount: int}
     */
    private function pixelData(TrackingPixel $pixel): array
    {
        return [
            'id' => $pixel->id,
            'name' => $pixel->name,
            'provider' => $pixel->provider,
            'providerId' => $pixel->provider_id,
            'isActive' => $pixel->is_active,
            'linkPagesCount' => $pixel->link_pages_count ?? 0,
        ];
    }
}
