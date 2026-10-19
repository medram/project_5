<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('links/index', [
            'links' => $request->user()->links()->latest()->get(),
        ]);
    }

    public function store(StoreLinkRequest $request): RedirectResponse
    {
        $request->user()->links()->create($request->validated());

        return to_route('links.index');
    }

    public function update(UpdateLinkRequest $request, Link $link): RedirectResponse
    {
        Gate::authorize('update', $link);

        $link->update($request->validated());

        return to_route('links.index');
    }

    public function destroy(Link $link): RedirectResponse
    {
        Gate::authorize('delete', $link);

        $link->delete();

        return to_route('links.index');
    }
}
