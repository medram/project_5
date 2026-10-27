<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderBioPageItemsRequest;
use App\Http\Requests\StoreBioContentBlockRequest;
use App\Http\Requests\UpdateBioContentBlockRequest;
use App\Models\BioContentBlock;
use App\Models\BioPage;
use App\Services\Content\ContentMediaStorage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BioContentBlockController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaces, private readonly ContentMediaStorage $media) {}

    public function store(StoreBioContentBlockRequest $request, BioPage $bioPage): RedirectResponse
    {
        $this->authorizePage($request, $bioPage);
        $data = $request->validated();
        $file = $request->file('image');
        $content = $this->content($data);
        unset($data['image'], $data['embed_url'], $data['image_url'], $data['media_id'], $data['content_media_id'], $data['disk'], $data['path']);
        $block = $bioPage->contentBlocks()->create([...$data, 'content' => $content, 'content_media_id' => null, 'position' => max((int) ($bioPage->bioLinks()->max('position') ?? -1), (int) ($bioPage->contentBlocks()->max('position') ?? -1)) + 1]);
        if ($file) {
            $media = null;
            try {
                $media = $this->media->store($file, $bioPage->workspace, $request->user(), 'image');
                $block->update(['content_media_id' => $media->id]);
            } catch (\Throwable $exception) {
                if ($media) {
                    $this->media->delete($media);
                }
                $block->delete();
                throw $exception;
            }
        }

        return to_route('bio-pages.show', $bioPage);
    }

    public function update(UpdateBioContentBlockRequest $request, BioPage $bioPage, BioContentBlock $bioBlock): RedirectResponse
    {
        $this->authorizePage($request, $bioPage);
        abort_unless($bioBlock->bio_page_id === $bioPage->id, 404);
        $data = $request->validated();
        $file = $request->file('image');
        $oldMedia = $bioBlock->contentMedia;
        $data['content'] = $this->content($data, $bioBlock);
        $newMedia = null;
        if ($file) {
            $newMedia = $this->media->store($file, $bioPage->workspace, $request->user(), 'image');
            $bioBlock->content_media_id = $newMedia->id;
        } elseif (($data['type'] ?? $bioBlock->type) !== 'image') {
            $bioBlock->content_media_id = null;
        }
        unset($data['image'], $data['embed_url'], $data['image_url'], $data['media_id'], $data['content_media_id'], $data['disk'], $data['path']);
        try {
            $bioBlock->fill($data)->save();
        } catch (\Throwable $exception) {
            if ($newMedia) {
                $this->media->delete($newMedia);
            }
            throw $exception;
        }
        if ($oldMedia && $oldMedia->id !== $bioBlock->content_media_id) {
            $this->media->delete($oldMedia);
        }

        return to_route('bio-pages.show', $bioPage);
    }

    public function destroy(Request $request, BioPage $bioPage, BioContentBlock $bioBlock): RedirectResponse
    {
        $this->authorizePage($request, $bioPage);
        abort_unless($bioBlock->bio_page_id === $bioPage->id, 404);
        if ($bioBlock->contentMedia) {
            $this->media->delete($bioBlock->contentMedia);
        }
        $bioBlock->delete();

        return to_route('bio-pages.show', $bioPage);
    }

    public function reorder(ReorderBioPageItemsRequest $request, BioPage $bioPage): RedirectResponse
    {
        $this->authorizePage($request, $bioPage);
        $items = $request->validated('items');
        $seen = [];
        foreach ($items as $item) {
            $key = $item['type'].':'.$item['id'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['items' => 'Each page item must appear exactly once.']);
            }
            $seen[$key] = true;
        }
        $expected = [];
        foreach ($bioPage->bioLinks()->pluck('id') as $id) {
            $expected[] = 'link:'.$id;
        }
        foreach ($bioPage->contentBlocks()->pluck('id') as $id) {
            $expected[] = 'block:'.$id;
        }
        sort($expected);
        $actual = array_keys($seen);
        sort($actual);
        if ($actual !== $expected) {
            throw ValidationException::withMessages(['items' => 'Order must include every link and block on this page exactly once.']);
        }
        DB::transaction(function () use ($bioPage, $items): void {
            foreach ($items as $position => $item) {
                ($item['type'] === 'link' ? $bioPage->bioLinks() : $bioPage->contentBlocks())->whereKey($item['id'])->update(['position' => $position]);
            }
        });

        return to_route('bio-pages.show', $bioPage);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function content(array $data, ?BioContentBlock $existing = null): array
    {
        $type = $data['type'] ?? $existing?->type;

        return match ($type) {
            'text' => ['text' => $data['text'] ?? $existing?->content['text'] ?? ''],
            'image' => ['alt_text' => $data['alt_text'] ?? $existing?->content['alt_text'] ?? ''],
            'social' => ['provider' => $data['provider'] ?? $existing?->content['provider'], 'identifier' => $data['identifier'] ?? $existing?->content['identifier']],
            'embed' => array_filter(['provider' => $data['provider'] ?? $existing?->content['provider'], 'identifier' => $data['identifier'] ?? $existing?->content['identifier'], 'provider_type' => $data['provider_type'] ?? $existing?->content['provider_type'] ?? null], fn ($value) => $value !== null),
            default => [],
        };
    }

    private function authorizePage(Request $request, BioPage $bioPage): void
    {
        abort_unless((int) $bioPage->workspace_id === (int) $this->workspaces->currentFor($request->user())->id, 404);
        Gate::authorize('update', $bioPage);
    }
}
