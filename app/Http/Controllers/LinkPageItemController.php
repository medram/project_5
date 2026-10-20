<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkPageItemRequest;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LinkPageItemController extends Controller
{
    public function store(
        StoreLinkPageItemRequest $request,
        LinkPage $linkPage,
    ): RedirectResponse {
        Gate::authorize('update', $linkPage);

        $position = $linkPage->items()->max('position');

        $linkPage->items()->create([
            ...$request->validated(),
            'position' => $position === null ? 0 : $position + 1,
        ]);

        return to_route('link-pages.show', $linkPage);
    }

    public function update(
        StoreLinkPageItemRequest $request,
        LinkPage $linkPage,
        LinkPageItem $linkPageItem,
    ): RedirectResponse {
        Gate::authorize('update', $linkPage);
        $this->ensureBelongsToPage($linkPage, $linkPageItem);

        $linkPageItem->update($request->validated());

        return to_route('link-pages.show', $linkPage);
    }

    public function destroy(
        LinkPage $linkPage,
        LinkPageItem $linkPageItem,
    ): RedirectResponse {
        Gate::authorize('update', $linkPage);
        $this->ensureBelongsToPage($linkPage, $linkPageItem);

        $linkPageItem->delete();

        return to_route('link-pages.show', $linkPage);
    }

    public function reorder(Request $request, LinkPage $linkPage): RedirectResponse
    {
        Gate::authorize('update', $linkPage);

        $itemIds = array_map('intval', $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer', 'distinct'],
        ])['item_ids']);
        $expectedIds = $linkPage->items()->pluck('id')->map('intval')->all();
        $providedIds = $itemIds;

        sort($expectedIds);
        sort($providedIds);

        if ($expectedIds !== $providedIds) {
            throw ValidationException::withMessages([
                'item_ids' => 'Item order must include every link page item exactly once.',
            ]);
        }

        DB::transaction(function () use ($itemIds, $linkPage): void {
            foreach ($itemIds as $position => $itemId) {
                $linkPage->items()->whereKey($itemId)->update([
                    'position' => $position,
                ]);
            }
        });

        return to_route('link-pages.show', $linkPage);
    }

    private function ensureBelongsToPage(LinkPage $linkPage, LinkPageItem $linkPageItem): void
    {
        abort_unless($linkPageItem->link_page_id === $linkPage->id, 404);
    }
}
