<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkRequest;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Models\Link;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    public function __construct(private readonly EnforcesPlanLimits $planLimits) {}

    public function index(Request $request): Response
    {
        return Inertia::render('links/index', [
            'links' => $request->user()->links()
                ->with('folder:id,name')
                ->with('customDomain:id,hostname,status')
                ->with('linkOverlay:id,name')
                ->withCount('clicks')
                ->latest()
                ->get(),
            'folders' => $request->user()->folders()
                ->orderBy('name')
                ->get(['id', 'name']),
            'customDomains' => $request->user()->customDomains()
                ->where('status', 'active')
                ->orderBy('hostname')
                ->get(['id', 'hostname']),
            'linkOverlays' => $request->user()->linkOverlays()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreLinkRequest $request): RedirectResponse
    {
        $this->planLimits->create(
            $request->user(),
            'links',
            fn ($user): Link => $user->links()->create($this->attributesFrom($request)),
        );

        return to_route('links.index');
    }

    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        Gate::authorize('update', $link);

        DB::transaction(function () use ($request, $link): void {
            $lockedLink = Link::query()->lockForUpdate()->findOrFail($link->id);

            $lockedLink->update($this->attributesFrom($request, $lockedLink));
        });

        return to_route('links.index');
    }

    public function destroy(Link $link): RedirectResponse
    {
        Gate::authorize('delete', $link);

        $link->delete();

        return to_route('links.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(LinkRequest $request, ?Link $link = null): array
    {
        $attributes = $request->validated();
        $accessCode = $attributes['access_code'] ?? null;
        $clearAccessCode = $request->boolean('clear_access_code');

        unset($attributes['access_code'], $attributes['clear_access_code']);

        if (is_string($accessCode) && $accessCode !== '') {
            $attributes['access_code'] = Hash::make($accessCode);

            if ($link !== null) {
                $attributes['access_code_version'] = $link->access_code_version + 1;
            }
        } elseif ($link !== null && $clearAccessCode && $link->requiresAccessCode()) {
            $attributes['access_code'] = null;
            $attributes['access_code_version'] = $link->access_code_version + 1;
        }

        return $attributes;
    }
}
