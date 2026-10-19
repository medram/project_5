<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderBioLinksRequest;
use App\Http\Requests\StoreBioLinkRequest;
use App\Http\Requests\UpdateBioLinkRequest;
use App\Models\BioLink;
use App\Models\BioPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BioLinkController extends Controller
{
    public function store(
        StoreBioLinkRequest $request,
        BioPage $bioPage,
    ): RedirectResponse {
        Gate::authorize('update', $bioPage);

        $position = $bioPage->bioLinks()->max('position');
        $position = $position === null ? 0 : $position + 1;

        $bioPage->bioLinks()->create([
            ...$request->validated(),
            'position' => $position,
        ]);

        return to_route('bio-pages.show', $bioPage);
    }

    public function update(
        UpdateBioLinkRequest $request,
        BioPage $bioPage,
        BioLink $bioLink,
    ): RedirectResponse {
        Gate::authorize('update', $bioPage);
        $this->ensureBelongsToPage($bioPage, $bioLink);

        $bioLink->update($request->validated());

        return to_route('bio-pages.show', $bioPage);
    }

    public function destroy(
        BioPage $bioPage,
        BioLink $bioLink,
    ): RedirectResponse {
        Gate::authorize('update', $bioPage);
        $this->ensureBelongsToPage($bioPage, $bioLink);

        $bioLink->delete();

        return to_route('bio-pages.show', $bioPage);
    }

    public function reorder(
        ReorderBioLinksRequest $request,
        BioPage $bioPage,
    ): RedirectResponse {
        Gate::authorize('update', $bioPage);

        $linkIds = array_map('intval', $request->validated('link_ids'));
        $providedIds = $linkIds;
        $expectedIds = $bioPage->bioLinks()->pluck('id')->all();

        sort($providedIds);
        sort($expectedIds);

        if ($providedIds !== $expectedIds) {
            throw ValidationException::withMessages([
                'link_ids' => 'Link order must include every link on this bio page exactly once.',
            ]);
        }

        DB::transaction(function () use ($bioPage, $linkIds): void {
            foreach ($linkIds as $position => $linkId) {
                $bioPage->bioLinks()->whereKey($linkId)->update([
                    'position' => $position,
                ]);
            }
        });

        return to_route('bio-pages.show', $bioPage);
    }

    private function ensureBelongsToPage(BioPage $bioPage, BioLink $bioLink): void
    {
        abort_unless($bioLink->bio_page_id === $bioPage->id, 404);
    }
}
