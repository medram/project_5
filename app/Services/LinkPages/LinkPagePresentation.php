<?php

namespace App\Services\LinkPages;

use App\Models\ContentMedia;
use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\LinkPageItem;
use App\Models\LinkPageVariant;
use Carbon\CarbonImmutable;

class LinkPagePresentation
{
    /** @return list<array<string, mixed>> */
    public function make(LinkPage $page, bool $editor = false, ?LinkPageVariant $variant = null): array
    {
        $now = CarbonImmutable::now('UTC');
        $linksQuery = $page->items();
        $blocksQuery = $page->blocks()->with('contentMedia');
        if ($variant !== null) {
            $linksQuery->where('link_page_variant_id', $variant->id);
            $blocksQuery->where('link_page_variant_id', $variant->id);
        }
        $links = $linksQuery->get()
            ->filter(fn (LinkPageItem $item): bool => $editor || $item->is_active)
            ->map(fn (LinkPageItem $item): array => $this->link($page, $item, $editor));
        $blocks = $blocksQuery->get()
            ->filter(fn (LinkPageBlock $block): bool => $editor || $this->eligible($block, $now))
            ->map(fn (LinkPageBlock $block): array => $this->block($page, $block, $editor, $now));

        return array_values($links->concat($blocks)->sort(function (array $left, array $right): int {
            return ($left['position'] <=> $right['position'])
                ?: strcmp($left['type'], $right['type'])
                ?: ($left['id'] <=> $right['id']);
        })->all());
    }

    public function eligible(LinkPageBlock $block, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now('UTC');

        return $block->is_active
            && ($block->starts_at === null || $block->starts_at->utc()->lessThanOrEqualTo($now))
            && ($block->ends_at === null || $block->ends_at->utc()->greaterThan($now));
    }

    /** @return array<string, mixed> */
    private function link(LinkPage $page, LinkPageItem $item, bool $editor): array
    {
        $data = [
            'type' => 'link',
            'id' => $item->id,
            'title' => $item->title,
            'position' => (int) $item->position,
        ];

        if ($editor) {
            return [
                ...$data,
                'linkPageVariantId' => $item->link_page_variant_id,
                'destinationUrl' => $item->destination_url,
                'isActive' => $item->is_active,
                'isPreviewVisible' => $item->is_active,
            ];
        }

        return [
            ...$data,
            'url' => "/pages/{$page->slug}/items/{$item->id}",
        ];
    }

    /** @return array<string, mixed> */
    private function block(LinkPage $page, LinkPageBlock $block, bool $editor, CarbonImmutable $now): array
    {
        $data = [
            'type' => $block->type,
            'id' => $block->id,
            'position' => $block->position,
        ];
        if ($editor) {
            $data += [
                'linkPageVariantId' => $block->link_page_variant_id,
                'isActive' => $block->is_active,
                'startsAt' => $block->starts_at?->toISOString(),
                'endsAt' => $block->ends_at?->toISOString(),
                'isPreviewVisible' => $this->eligible($block, $now),
            ];
        }

        if ($block->type === 'form') {
            $form = [...$data, 'fields' => $this->formFields($block)];
            if (! $editor) {
                $form['submitUrl'] = "/pages/{$page->slug}/blocks/{$block->id}/leads";
            }

            return $form;
        }
        if ($block->type === 'image') {
            $media = $block->contentMedia;
            $imageUrl = null;
            if ($media !== null && $this->usableImage($media, $page)) {
                $imageUrl = $editor
                    ? "/dashboard/link-pages/{$page->id}/blocks/{$block->id}/image"
                    : "/content-media/{$media->public_id}";
            }

            return [
                ...$data,
                'altText' => $block->content['alt_text'] ?? '',
                'imageUrl' => $imageUrl,
            ];
        }
        if ($block->type === 'file') {
            $media = $block->contentMedia;
            $fileName = null;
            $fileUrl = null;
            if ($media !== null && $this->usableFile($media, $page)) {
                $fileName = $media->original_name;
                if (! $editor) {
                    $fileUrl = "/content-media/{$media->public_id}";
                }
            }

            return [
                ...$data,
                'fileName' => $fileName,
                'fileUrl' => $fileUrl,
            ];
        }

        return [
            ...$data,
            'text' => $block->content['text'] ?? '',
        ];
    }

    /** @return list<array{id: string, label: string, type: string, required: bool, options?: list<string>}> */
    private function formFields(LinkPageBlock $block): array
    {
        $fields = $block->content['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields)) {
            return [];
        }

        $result = [];
        foreach ($fields as $field) {
            if (
                ! is_array($field)
                || ! is_string($field['id'] ?? null)
                || ! is_string($field['label'] ?? null)
                || ! is_string($field['type'] ?? null)
                || ! in_array($field['type'], ['text', 'email', 'textarea', 'select'], true)
            ) {
                continue;
            }
            $safeField = [
                'id' => $field['id'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
            ];
            if ($field['type'] === 'select' && is_array($field['options'] ?? null)) {
                $safeField['options'] = array_values(array_filter($field['options'], 'is_string'));
            }
            $result[] = $safeField;
        }

        return $result;
    }

    private function usableImage(?ContentMedia $media, LinkPage $page): bool
    {
        return $media !== null
            && (int) $media->workspace_id === (int) $page->workspace_id
            && ! $media->trashed()
            && ($media->expires_at === null || $media->expires_at->isFuture())
            && $media->visibility === 'private'
            && in_array($media->scan_state, ['not_required', 'clean'], true)
            && in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    private function usableFile(?ContentMedia $media, LinkPage $page): bool
    {
        return $media !== null
            && (int) $media->workspace_id === (int) $page->workspace_id
            && ! $media->trashed()
            && ($media->expires_at === null || $media->expires_at->isFuture())
            && $media->visibility === 'private'
            && $media->scan_state === 'clean'
            && $media->mime_type === 'application/pdf';
    }
}
