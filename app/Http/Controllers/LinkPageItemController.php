<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLinkPageItemRequest;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\LinkPageVariant;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LinkPageItemController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function store(
        StoreLinkPageItemRequest $request,
        LinkPage $linkPage,
    ): RedirectResponse {
        $this->ensureInCurrentWorkspace($request, $linkPage);
        Gate::authorize('update', $linkPage);

        $data = $request->validated();
        $variant = $this->variantForPage(
            $linkPage,
            isset($data['link_page_variant_id']) ? (int) $data['link_page_variant_id'] : null,
        );
        $position = max(
            (int) ($linkPage->items()->where('link_page_variant_id', $variant->id)->max('position') ?? -1),
            (int) ($linkPage->blocks()->where('link_page_variant_id', $variant->id)->max('position') ?? -1),
        ) + 1;

        $linkPage->items()->create([
            ...$data,
            'link_page_variant_id' => $variant->id,
            'position' => $position,
        ]);

        return $this->editorResponse($linkPage, $variant);
    }

    public function update(
        StoreLinkPageItemRequest $request,
        LinkPage $linkPage,
        LinkPageItem $linkPageItem,
    ): RedirectResponse {
        $this->ensureInCurrentWorkspace($request, $linkPage);
        Gate::authorize('update', $linkPage);
        $this->ensureBelongsToPage($linkPage, $linkPageItem);

        $data = $request->validated();
        $variant = $this->variantForPage(
            $linkPage,
            isset($data['link_page_variant_id'])
                ? (int) $data['link_page_variant_id']
                : $linkPageItem->link_page_variant_id,
        );
        $attributes = [
            ...$data,
            'link_page_variant_id' => $variant->id,
        ];
        if ((int) $linkPageItem->link_page_variant_id !== (int) $variant->id) {
            $attributes['position'] = max(
                (int) ($linkPage->items()->where('link_page_variant_id', $variant->id)->max('position') ?? -1),
                (int) ($linkPage->blocks()->where('link_page_variant_id', $variant->id)->max('position') ?? -1),
            ) + 1;
        }
        $linkPageItem->update($attributes);

        return $this->editorResponse($linkPage, $variant);
    }

    public function destroy(
        Request $request,
        LinkPage $linkPage,
        LinkPageItem $linkPageItem,
    ): RedirectResponse {
        $this->ensureInCurrentWorkspace($request, $linkPage);
        Gate::authorize('update', $linkPage);
        $this->ensureBelongsToPage($linkPage, $linkPageItem);

        $linkPageItem->delete();

        return $this->editorResponse(
            $linkPage,
            $this->variantForPage(
                $linkPage,
                $linkPageItem->link_page_variant_id === null
                    ? null
                    : (int) $linkPageItem->link_page_variant_id,
            ),
        );
    }

    public function reorder(Request $request, LinkPage $linkPage): RedirectResponse
    {
        $this->ensureInCurrentWorkspace($request, $linkPage);
        Gate::authorize('update', $linkPage);

        $validated = $request->validate([
            'variant_id' => ['sometimes', 'integer', 'min:1'],
            'items' => ['required', 'array'],
            'items.*' => ['required', 'array:type,id'],
            'items.*.type' => ['required', 'in:link,block'],
            'items.*.id' => ['required', 'integer', 'min:1'],
        ]);
        $variant = $this->variantForPage(
            $linkPage,
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
        );
        $items = $validated['items'];
        $seen = [];

        foreach ($items as $item) {
            $key = $item['type'].':'.(int) $item['id'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'items' => 'Each page item must appear exactly once.',
                ]);
            }
            $seen[$key] = true;
        }

        $expected = [];
        foreach ($linkPage->items()->where('link_page_variant_id', $variant->id)->pluck('id') as $id) {
            $expected[] = 'link:'.(int) $id;
        }
        foreach ($linkPage->blocks()->where('link_page_variant_id', $variant->id)->pluck('id') as $id) {
            $expected[] = 'block:'.(int) $id;
        }
        sort($expected);
        $provided = array_keys($seen);
        sort($provided);

        if ($expected !== $provided) {
            throw ValidationException::withMessages([
                'items' => 'Order must include every link and block in this variant exactly once.',
            ]);
        }

        DB::transaction(function () use ($items, $linkPage, $variant): void {
            foreach ($items as $position => $item) {
                $relation = $item['type'] === 'link' ? $linkPage->items() : $linkPage->blocks();
                $relation
                    ->where('link_page_variant_id', $variant->id)
                    ->whereKey((int) $item['id'])
                    ->update(['position' => $position]);
            }
        });

        return $this->editorResponse($linkPage, $variant);
    }

    private function editorResponse(LinkPage $linkPage, LinkPageVariant $variant): RedirectResponse
    {
        return $variant->is_control
            ? to_route('link-pages.show', $linkPage)
            : to_route('link-pages.show', [
                'linkPage' => $linkPage,
                'variant' => $variant->id,
            ]);
    }

    private function variantForPage(LinkPage $linkPage, ?int $variantId): LinkPageVariant
    {
        $variant = $variantId === null
            ? $linkPage->controlVariant()->where('is_active', true)->first()
            : $linkPage->variants()->whereKey($variantId)->first();
        if ($variant === null) {
            throw ValidationException::withMessages([
                'link_page_variant_id' => 'Select a variant that belongs to this page.',
            ]);
        }

        return $variant;
    }

    private function ensureBelongsToPage(LinkPage $linkPage, LinkPageItem $linkPageItem): void
    {
        abort_unless($linkPageItem->link_page_id === $linkPage->id, 404);
    }

    private function ensureInCurrentWorkspace(Request $request, LinkPage $linkPage): void
    {
        abort_unless(
            (int) $linkPage->workspace_id === (int) $this->workspaces->currentFor($request->user())->id,
            404,
        );
    }
}
