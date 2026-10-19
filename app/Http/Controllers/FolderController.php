<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FolderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('folders/index', [
            'folders' => $request->user()->folders()
                ->withCount(['links', 'clicks'])
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreFolderRequest $request): RedirectResponse
    {
        $request->user()->folders()->create($request->validated());

        return to_route('folders.index');
    }

    public function update(UpdateFolderRequest $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        $folder->update($request->validated());

        return to_route('folders.index');
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $folder->delete();

        return to_route('folders.index');
    }
}
