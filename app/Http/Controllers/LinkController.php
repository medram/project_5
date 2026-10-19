<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinkRequest;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('links/index', [
            'links' => $request->user()->links()
                ->with('folder:id,name')
                ->withCount('clicks')
                ->latest()
                ->get(),
            'folders' => $request->user()->folders()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreLinkRequest $request): RedirectResponse
    {
        $request->user()->links()->create($this->attributesFrom($request));

        return to_route('links.index');
    }

    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        Gate::authorize('update', $link);

        $link->update($this->attributesFrom($request, $link));

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
        } elseif ($link !== null && $clearAccessCode) {
            $attributes['access_code'] = null;
        }

        return $attributes;
    }
}
