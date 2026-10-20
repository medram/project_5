<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkPageRequest;
use App\Models\LinkPage;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LinkPageController extends Controller
{
    public function __construct(private readonly EnforcesPlanLimits $planLimits) {}

    public function index(Request $request): Response
    {
        return Inertia::render('link-pages/index', [
            'linkPages' => $request->user()->linkPages()
                ->with(['trackingPixels:id'])
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
        $attributes = $this->pageAttributes($request);
        $pixelIds = $this->pixelIds($request);

        $linkPage = $this->planLimits->create(
            $request->user(),
            'link_pages',
            function (User $user) use ($attributes, $pixelIds): LinkPage {
                $linkPage = $user->linkPages()->create($attributes);
                $this->syncTrackingPixels($linkPage, $user, $pixelIds);

                return $linkPage;
            },
        );

        return to_route('link-pages.show', $linkPage);
    }

    public function show(LinkPage $linkPage): Response
    {
        Gate::authorize('view', $linkPage);

        $linkPage->load([
            'trackingPixels' => fn ($query) => $query->orderBy('tracking_pixels.name'),
        ]);

        return Inertia::render('link-pages/edit', [
            'linkPage' => $this->pageData($linkPage),
            'items' => $linkPage->items()
                ->get()
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'destinationUrl' => $item->destination_url,
                    'position' => $item->position,
                    'isActive' => $item->is_active,
                ])
                ->values()
                ->all(),
            'availablePixels' => $linkPage->user->trackingPixels()
                ->orderBy('name')
                ->get()
                ->map(fn (TrackingPixel $pixel): array => $this->pixelData($pixel))
                ->values()
                ->all(),
        ]);
    }

    public function update(
        StoreLinkPageRequest $request,
        LinkPage $linkPage,
    ): RedirectResponse {
        Gate::authorize('update', $linkPage);

        $attributes = $this->pageAttributes($request);
        $pixelIds = $this->pixelIds($request);

        DB::transaction(function () use ($attributes, $linkPage, $pixelIds, $request): void {
            $linkPage->update($attributes);
            $this->syncTrackingPixels($linkPage, $request->user(), $pixelIds);
        });

        return to_route('link-pages.show', $linkPage);
    }

    public function destroy(LinkPage $linkPage): RedirectResponse
    {
        Gate::authorize('delete', $linkPage);

        $linkPage->delete();

        return to_route('link-pages.index');
    }

    /**
     * @return array{name: string, slug: string, title: string, description: string|null, is_published: bool}
     */
    private function pageAttributes(StoreLinkPageRequest $request): array
    {
        $attributes = $request->validated();
        unset($attributes['tracking_pixel_ids']);

        return $attributes;
    }

    /**
     * @return array<int, int>
     */
    private function pixelIds(StoreLinkPageRequest $request): array
    {
        $pixelIds = $request->validated('tracking_pixel_ids', []);

        return is_array($pixelIds) ? array_map('intval', $pixelIds) : [];
    }

    /**
     * @param  array<int, int>  $pixelIds
     */
    private function syncTrackingPixels(LinkPage $linkPage, User $user, array $pixelIds): void
    {
        if (count($pixelIds) > 5 || count($pixelIds) !== count(array_unique($pixelIds))) {
            throw ValidationException::withMessages([
                'tracking_pixel_ids' => 'A link page can use up to five distinct active pixels.',
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

        $linkPage->trackingPixels()->sync($pixelIds);
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
     * @return array{id: int, name: string, slug: string, title: string, description: string|null, isPublished: bool, itemsCount: int, trackingPixelIds: array<int, int>, publicUrl: string}
     */
    private function pageData(LinkPage $linkPage): array
    {
        return [
            'id' => $linkPage->id,
            'name' => $linkPage->name,
            'slug' => $linkPage->slug,
            'title' => $linkPage->title,
            'description' => $linkPage->description,
            'isPublished' => $linkPage->is_published,
            'itemsCount' => $linkPage->items_count ?? 0,
            'trackingPixelIds' => $linkPage->trackingPixels
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
                ->all(),
            'publicUrl' => url("/pages/{$linkPage->slug}"),
        ];
    }
}
