<?php

namespace App\Http\Controllers;

use App\Models\LinkPage;
use App\Models\LinkPageVariant;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LinkPageVariantController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces) {}

    public function store(Request $request, LinkPage $linkPage): RedirectResponse
    {
        $this->authorizePage($request, $linkPage);
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('link_page_variants', 'name')->where('link_page_id', $linkPage->id),
            ],
            'weight' => ['required', 'integer', 'min:0', 'max:100'],
            'is_control' => ['prohibited'],
            'is_active' => ['prohibited'],
        ]);

        $variant = DB::transaction(function () use ($data, $linkPage): LinkPageVariant {
            $lockedPage = LinkPage::query()->lockForUpdate()->findOrFail($linkPage->id);
            $control = $lockedPage->controlVariant()->lockForUpdate()->firstOrFail();
            $assignedWeight = (int) $lockedPage->variants()
                ->where('is_active', true)
                ->where('is_control', false)
                ->sum('weight') + (int) $data['weight'];

            if ($assignedWeight > 100) {
                throw ValidationException::withMessages([
                    'weight' => 'Active variant weights cannot exceed 100%.',
                ]);
            }

            $variant = $lockedPage->variants()->create([
                'name' => $data['name'],
                'weight' => (int) $data['weight'],
                'is_control' => false,
                'is_active' => true,
            ]);
            $control->update(['weight' => 100 - $assignedWeight]);

            return $variant;
        });

        return $this->editorResponse($linkPage, $variant);
    }

    public function update(
        Request $request,
        LinkPage $linkPage,
        LinkPageVariant $linkPageVariant,
    ): RedirectResponse {
        $this->authorizePage($request, $linkPage);
        abort_unless((int) $linkPageVariant->link_page_id === (int) $linkPage->id, 404);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('link_page_variants', 'name')
                    ->where('link_page_id', $linkPage->id)
                    ->ignore($linkPageVariant->id),
            ],
            'weight' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'is_control' => ['prohibited'],
        ]);

        DB::transaction(function () use ($data, $linkPage, $linkPageVariant): void {
            $lockedPage = LinkPage::query()->lockForUpdate()->findOrFail($linkPage->id);
            $variant = $lockedPage->variants()->lockForUpdate()->findOrFail($linkPageVariant->id);
            $control = $lockedPage->controlVariant()->lockForUpdate()->firstOrFail();

            if ($variant->is_control) {
                if (! $data['is_active']) {
                    throw ValidationException::withMessages([
                        'is_active' => 'The control variant must remain active.',
                    ]);
                }
                $variant->update(['name' => $data['name']]);

                return;
            }

            $active = (bool) $data['is_active'];
            $weight = $active ? (int) $data['weight'] : 0;
            $otherWeight = (int) $lockedPage->variants()
                ->where('is_active', true)
                ->where('is_control', false)
                ->whereKeyNot($variant->id)
                ->sum('weight');
            $assignedWeight = $otherWeight + $weight;

            if ($assignedWeight > 100) {
                throw ValidationException::withMessages([
                    'weight' => 'Active variant weights cannot exceed 100%.',
                ]);
            }

            $variant->update([
                'name' => $data['name'],
                'weight' => $weight,
                'is_active' => $active,
            ]);
            $control->update(['weight' => 100 - $assignedWeight]);
        });

        return $this->editorResponse($linkPage, $linkPageVariant);
    }

    public function destroy(
        Request $request,
        LinkPage $linkPage,
        LinkPageVariant $linkPageVariant,
    ): RedirectResponse {
        $this->authorizePage($request, $linkPage);
        abort_unless((int) $linkPageVariant->link_page_id === (int) $linkPage->id, 404);

        DB::transaction(function () use ($linkPage, $linkPageVariant): void {
            $lockedPage = LinkPage::query()->lockForUpdate()->findOrFail($linkPage->id);
            $variant = $lockedPage->variants()->lockForUpdate()->findOrFail($linkPageVariant->id);
            if ($variant->is_control) {
                throw ValidationException::withMessages([
                    'variant' => 'The control variant cannot be deleted.',
                ]);
            }
            $control = $lockedPage->controlVariant()->lockForUpdate()->firstOrFail();

            $entries = [];
            foreach ($lockedPage->items()->where('link_page_variant_id', $variant->id)->get() as $item) {
                $entries[] = ['type' => 'link', 'id' => $item->id, 'position' => $item->position];
            }
            foreach ($lockedPage->blocks()->where('link_page_variant_id', $variant->id)->get() as $block) {
                $entries[] = ['type' => 'block', 'id' => $block->id, 'position' => $block->position];
            }
            usort($entries, static fn (array $left, array $right): int => [$left['position'], $left['type'], $left['id']] <=> [$right['position'], $right['type'], $right['id']]
            );

            $position = max(
                (int) ($lockedPage->items()->where('link_page_variant_id', $control->id)->max('position') ?? -1),
                (int) ($lockedPage->blocks()->where('link_page_variant_id', $control->id)->max('position') ?? -1),
            ) + 1;
            foreach ($entries as $entry) {
                $relation = $entry['type'] === 'link' ? $lockedPage->items() : $lockedPage->blocks();
                $relation->whereKey($entry['id'])->update([
                    'link_page_variant_id' => $control->id,
                    'position' => $position++,
                ]);
            }

            $control->update([
                'weight' => $control->weight + ($variant->is_active ? $variant->weight : 0),
            ]);
            $variant->delete();

        });

        return to_route('link-pages.show', $linkPage);
    }

    private function authorizePage(Request $request, LinkPage $linkPage): void
    {
        $workspace = $this->workspaces->currentFor($request->user());
        abort_unless((int) $linkPage->workspace_id === (int) $workspace->id, 404);
        Gate::authorize('update', $linkPage);
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
}
