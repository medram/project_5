<?php

namespace App\Services\BioPages;

use App\Models\BioContentBlock;
use App\Models\BioLink;
use App\Models\BioPage;
use Carbon\CarbonImmutable;
use Closure;

class BioPagePresentation
{
    public const DEFAULT_APPEARANCE = ['theme' => 'light', 'typography' => 'sans', 'color' => 'blue', 'background' => 'plain'];

    /** @return array{handle: string, displayName: string, bio: string|null, appearance: array<string, string>, items: list<array<string, mixed>>} */
    public function make(BioPage $page, bool $editor = false, ?Closure $editorImageUrl = null): array
    {
        $now = CarbonImmutable::now('UTC');
        $links = $page->bioLinks()->get()->filter(fn (BioLink $link): bool => $editor || $this->eligible($link, $now))->map(fn (BioLink $link): array => [
            'type' => 'link', 'id' => $link->id, 'title' => $link->title,
            'url' => "/bio/{$page->handle}/links/{$link->id}", 'position' => $link->position,
            'isPinned' => $link->is_pinned, 'isActive' => $link->is_active,
            'startsAt' => $link->starts_at?->toISOString(), 'endsAt' => $link->ends_at?->toISOString(),
        ]);
        $blocks = $page->contentBlocks()->with('contentMedia')->get()->filter(fn (BioContentBlock $block): bool => $editor || $this->eligible($block, $now))->map(fn (BioContentBlock $block): array => $this->block($page, $block, $editor, $editorImageUrl));
        $items = array_values($links->concat($blocks)->sort(function (array $a, array $b): int {
            return ((int) $b['isPinned'] <=> (int) $a['isPinned']) ?: ($a['position'] <=> $b['position']) ?: ($a['type'] <=> $b['type']) ?: ($a['id'] <=> $b['id']);
        })->all());

        return ['handle' => $page->handle, 'displayName' => $page->display_name, 'bio' => $page->bio, 'appearance' => array_replace(self::DEFAULT_APPEARANCE, $page->appearance ?? []), 'items' => $items];
    }

    /** @param BioLink|BioContentBlock $item */
    public function eligible(object $item, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now('UTC');

        return $item->is_active && ($item->starts_at === null || $item->starts_at->utc()->lessThanOrEqualTo($now)) && ($item->ends_at === null || $item->ends_at->utc()->greaterThan($now));
    }

    /** @return array<string, mixed> */
    private function block(BioPage $page, BioContentBlock $block, bool $editor, ?Closure $editorImageUrl): array
    {
        $data = ['type' => $block->type, 'id' => $block->id, 'position' => $block->position, 'isPinned' => $block->is_pinned, 'isActive' => $block->is_active, 'startsAt' => $block->starts_at?->toISOString(), 'endsAt' => $block->ends_at?->toISOString()];
        if ($block->type === 'image') {
            $media = $block->contentMedia;
            $usable = $media && $media->workspace_id === $page->workspace_id && ! $media->trashed() && (! $media->expires_at || $media->expires_at->isFuture()) && $media->visibility === 'private' && in_array($media->scan_state, ['not_required', 'clean'], true);

            return [
                ...$data,
                'altText' => $block->content['alt_text'] ?? '',
                'imageUrl' => $usable
                    ? ($editor
                        ? ($editorImageUrl ? $editorImageUrl($page, $block) : "/dashboard/biolinks/{$page->id}/blocks/{$block->id}/image")
                        : "/bio/{$page->handle}/blocks/{$block->id}/image")
                    : null,
            ];
        }
        if ($block->type === 'social') {
            return [...$data, 'provider' => $block->content['provider'], 'identifier' => $block->content['identifier'], 'url' => "/bio/{$page->handle}/blocks/{$block->id}"];
        }
        if ($block->type === 'embed') {
            $provider = $block->content['provider'];
            $identifier = $block->content['identifier'];
            $providerType = $block->content['provider_type'] ?? null;
            $base = rtrim((string) config('app.url'), '/');
            $embedUrl = match ($provider) {
                'youtube' => "https://www.youtube-nocookie.com/embed/{$identifier}",
                'soundcloud' => 'https://w.soundcloud.com/player/?url='.rawurlencode("https://api.soundcloud.com/tracks/{$identifier}"),
                'vimeo' => "https://player.vimeo.com/video/{$identifier}",
                'spotify' => $providerType !== null ? "https://open.spotify.com/embed/{$providerType}/{$identifier}" : abort(404),
                'twitch' => "https://player.twitch.tv/?channel={$identifier}&parent=".rawurlencode((string) parse_url($base, PHP_URL_HOST)),
                'tiktok' => "https://www.tiktok.com/player/v1/{$identifier}",
                default => abort(404),
            };

            return [...$data, 'provider' => $provider, 'identifier' => $identifier, 'providerType' => $providerType, 'embedUrl' => $embedUrl];
        }

        return [...$data, 'text' => $block->content['text'] ?? ''];
    }
}
