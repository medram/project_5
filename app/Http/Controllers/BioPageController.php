<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBioPageRequest;
use App\Http\Requests\UpdateBioPageRequest;
use App\Models\BioPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BioPageController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('bio-pages/index', [
            'bioPages' => $request->user()->bioPages()->latest()->get(),
        ]);
    }

    public function store(StoreBioPageRequest $request): RedirectResponse
    {
        $bioPage = $request->user()->bioPages()->create($request->validated());

        return to_route('bio-pages.show', $bioPage);
    }

    public function show(BioPage $bioPage): Response
    {
        Gate::authorize('view', $bioPage);

        return Inertia::render('bio-pages/edit', [
            'bioPage' => $bioPage,
            'bioLinks' => $bioPage->bioLinks()->get(),
        ]);
    }

    public function update(
        UpdateBioPageRequest $request,
        BioPage $bioPage,
    ): RedirectResponse {
        Gate::authorize('update', $bioPage);

        $bioPage->update($request->validated());

        return to_route('bio-pages.show', $bioPage);
    }

    public function destroy(BioPage $bioPage): RedirectResponse
    {
        Gate::authorize('delete', $bioPage);

        $bioPage->delete();

        return to_route('bio-pages.index');
    }
}
