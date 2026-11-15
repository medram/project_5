<?php

namespace App\Services\Links;

use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\LinkRevision;
use App\Models\LinkRoutingRule;
use App\Models\LinkTag;
use App\Models\User;

class LinkRevisionService
{
    public function __construct(
        private readonly FirstPartyDestinationGuard $destinations,
        private readonly LinkPolicy $policy,
    ) {}

    /** @var array<int, string> */
    private const SNAPSHOT_FIELDS = [
        'folder_id',
        'custom_domain_id',
        'link_overlay_id',
        'link_page_id',
        'destination_url',
        'title',
        'slug',
        'is_active',
        'access_code',
        'access_code_version',
        'available_from',
        'expires_at',
        'delivery_mode',
        'archived_at',
        'max_clicks',
        'approval_required',
        'utm_parameters',
        'custom_parameters',
    ];

    /** @param array<int, int> $tagIds @param array<int, array<string, mixed>> $routingRules */
    public function recordInitial(Link $link, array $tagIds, array $routingRules, User $actor): LinkRevision
    {
        $link->refresh();

        $link->forceFill([
            'approval_status' => $link->approval_required ? 'pending' : 'approved',
        ])->save();
        $this->syncTagsAndRules($link, $tagIds, $routingRules);

        return $this->createRevision(
            $link,
            $actor,
            $link->approval_required ? 'pending' : 'applied',
            $this->snapshot($link, [], $tagIds, $routingRules),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>|null  $tagIds
     * @param  array<int, array<string, mixed>>|null  $routingRules
     */
    public function update(Link $link, array $attributes, ?array $tagIds, ?array $routingRules, User $actor): LinkRevision
    {
        $approvalRequired = (bool) ($attributes['approval_required'] ?? $link->approval_required);
        if ($link->approval_required && $approvalRequired) {
            return $this->createRevision(
                $link,
                $actor,
                'pending',
                $this->snapshot($link, $attributes, $tagIds, $routingRules),
            );
        }

        if ($attributes !== []) {
            $link->update($attributes);
        }
        if ($tagIds !== null || $routingRules !== null) {
            $this->syncTagsAndRules(
                $link,
                $tagIds ?? $this->currentTagIds($link),
                $routingRules ?? $this->currentRoutingRules($link),
            );
        }
        $link->forceFill([
            'approval_status' => 'approved',
            'revision_version' => $link->revision_version + 1,
        ])->save();

        return $this->createRevision(
            $link,
            $actor,
            'applied',
            $this->snapshot($link, [], $this->currentTagIds($link), $this->currentRoutingRules($link)),
        );
    }

    public function approve(Link $link, LinkRevision $revision, User $reviewer): bool
    {
        if ($revision->status !== 'pending') {
            return false;
        }

        $snapshot = $revision->snapshot;
        if ((int) ($snapshot['base_version'] ?? 0) !== (int) $link->revision_version || ! $this->referencesStillValid($link, $snapshot)) {
            $revision->update([
                'status' => 'stale',
                'reviewer_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return false;
        }

        $attributes = array_intersect_key($snapshot, array_flip(self::SNAPSHOT_FIELDS));
        $link->forceFill($attributes);
        $link->forceFill([
            'approval_status' => 'approved',
            'revision_version' => $link->revision_version + 1,
        ])->save();
        $this->syncTagsAndRules(
            $link,
            array_map('intval', $snapshot['tag_ids'] ?? []),
            $snapshot['routing_rules'] ?? [],
        );
        $revision->update([
            'status' => 'approved',
            'reviewer_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return true;
    }

    public function reject(LinkRevision $revision, User $reviewer, ?string $note): bool
    {
        if ($revision->status !== 'pending') {
            return false;
        }

        $revision->update([
            'status' => 'rejected',
            'reviewer_user_id' => $reviewer->id,
            'review_note' => $note,
            'reviewed_at' => now(),
        ]);

        return true;
    }

    /** @param array<string, mixed> $attributes @param array<int, int>|null $tagIds @param array<int, array<string, mixed>>|null $routingRules @return array<string, mixed> */
    private function snapshot(Link $link, array $attributes, ?array $tagIds, ?array $routingRules): array
    {
        $snapshot = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = array_key_exists($field, $attributes)
                ? $attributes[$field]
                : $link->getAttribute($field);
        }

        $snapshot['base_version'] = (int) $link->revision_version;
        $snapshot['tag_ids'] = $tagIds === null ? $this->currentTagIds($link) : array_values(array_map('intval', $tagIds));
        $snapshot['routing_rules'] = $routingRules === null
            ? $this->currentRoutingRules($link)
            : array_values(array_map(fn (array $rule): array => [
                'rule_type' => $rule['rule_type'],
                'match_value' => $rule['match_value'],
                'target_url' => $rule['target_url'],
            ], $routingRules));

        $workspaceId = (int) $link->workspace_id;
        $folderId = $snapshot['folder_id'];
        $customDomainId = $snapshot['custom_domain_id'];
        $overlayId = $snapshot['link_overlay_id'];
        $pageId = $snapshot['link_page_id'];
        $tagNames = LinkTag::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($snapshot['tag_ids'])
            ->pluck('name', 'id');
        $page = $pageId === null
            ? null
            : LinkPage::query()->where('workspace_id', $workspaceId)->find($pageId);

        $snapshot['display_labels'] = [
            'folder' => $folderId === null
                ? null
                : Folder::query()->where('workspace_id', $workspaceId)->whereKey($folderId)->value('name'),
            'custom_domain' => $customDomainId === null
                ? null
                : CustomDomain::query()->where('workspace_id', $workspaceId)->whereKey($customDomainId)->value('hostname'),
            'overlay' => $overlayId === null
                ? null
                : LinkOverlay::query()->where('workspace_id', $workspaceId)->whereKey($overlayId)->value('name'),
            'link_page' => $page === null ? null : $page->name.' · /'.$page->slug,
            'tags' => array_values(array_filter(
                array_map(fn (int $id): ?string => $tagNames->get($id), $snapshot['tag_ids']),
                static fn (?string $name): bool => $name !== null,
            )),
        ];

        return $snapshot;
    }

    /** @param array<string, mixed> $snapshot */
    private function referencesStillValid(Link $link, array $snapshot): bool
    {
        if (($snapshot['delivery_mode'] ?? null) !== 'link_page'
            && ! $this->isExternalDestinationValid(
                $snapshot['destination_url'] ?? null,
                $snapshot['slug'] ?? null,
                $snapshot['title'] ?? null,
            )) {
            return false;
        }

        foreach ($snapshot['routing_rules'] ?? [] as $rule) {
            if (! is_array($rule) || ! $this->isExternalDestinationValid(
                $rule['target_url'] ?? null,
                $snapshot['slug'] ?? null,
                $snapshot['title'] ?? null,
            )) {
                return false;
            }
        }

        $slug = $snapshot['slug'] ?? null;
        if (! is_string($slug)
            || in_array($slug, (array) config('linkpilot.reserved_slugs', []), true)
            || Link::query()->where('slug', $slug)->where('id', '!=', $link->getKey())->exists()) {
            return false;
        }

        $workspaceId = $link->workspace_id;
        if (($snapshot['folder_id'] ?? null) !== null && ! Folder::query()->where('workspace_id', $workspaceId)->whereKey($snapshot['folder_id'])->exists()) {
            return false;
        }

        $tagIds = array_map('intval', $snapshot['tag_ids'] ?? []);
        if ($tagIds !== [] && LinkTag::query()->where('workspace_id', $workspaceId)->whereKey($tagIds)->count() !== count(array_unique($tagIds))) {
            return false;
        }

        $customDomainId = $snapshot['custom_domain_id'] ?? null;
        if ($customDomainId !== null) {
            $domain = CustomDomain::query()->where('workspace_id', $workspaceId)->find($customDomainId);
            if ($domain === null || ($domain->status !== 'active' && (int) $link->custom_domain_id !== (int) $customDomainId)) {
                return false;
            }
        }

        $overlayId = $snapshot['link_overlay_id'] ?? null;
        if ($overlayId === null && ($snapshot['delivery_mode'] ?? null) === 'overlay') {
            return false;
        }
        if ($overlayId !== null) {
            $overlay = LinkOverlay::query()->where('workspace_id', $workspaceId)->find($overlayId);
            if ($overlay === null || (($snapshot['delivery_mode'] ?? null) === 'overlay' && ! $overlay->is_active)) {
                return false;
            }
        }

        $pageId = $snapshot['link_page_id'] ?? null;
        if (($snapshot['delivery_mode'] ?? null) === 'link_page') {
            return $pageId !== null && LinkPage::query()
                ->where('workspace_id', $workspaceId)
                ->where('is_published', true)
                ->whereKey($pageId)
                ->exists();
        }

        return $pageId === null;
    }

    private function isExternalDestinationValid(mixed $destination, mixed $slug = null, mixed $title = null): bool
    {
        return is_string($destination)
            && in_array(strtolower((string) parse_url($destination, PHP_URL_SCHEME)), ['http', 'https'], true)
            && is_string(parse_url($destination, PHP_URL_HOST))
            && ! $this->destinations->rejects($destination)
            && $this->policy->rejection(
                $destination,
                is_string($slug) ? $slug : null,
                is_string($title) ? $title : null,
            ) === null;
    }

    /** @param array<int, int> $tagIds @param array<int, array<string, mixed>> $routingRules */
    private function syncTagsAndRules(Link $link, array $tagIds, array $routingRules): void
    {
        $link->tags()->sync($tagIds);
        LinkRoutingRule::query()->where('link_id', $link->getKey())->delete();

        foreach ($routingRules as $position => $rule) {
            $link->routingRules()->create([
                'workspace_id' => $link->workspace_id,
                'position' => $position + 1,
                'rule_type' => $rule['rule_type'],
                'match_value' => $rule['match_value'],
                'target_url' => $rule['target_url'],
            ]);
        }

        $link->unsetRelation('tags');
        $link->unsetRelation('routingRules');
    }

    /** @return array<int, int> */
    private function currentTagIds(Link $link): array
    {
        return $link->tags()->orderBy('link_tags.id')->pluck('link_tags.id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return array<int, array<string, string>> */
    private function currentRoutingRules(Link $link): array
    {
        return $link->routingRules()->orderBy('position')->get(['rule_type', 'match_value', 'target_url'])
            ->map(fn (LinkRoutingRule $rule): array => [
                'rule_type' => $rule->rule_type,
                'match_value' => $rule->match_value,
                'target_url' => $rule->target_url,
            ])->all();
    }

    /** @param array<string, mixed> $snapshot */
    private function createRevision(Link $link, User $actor, string $status, array $snapshot): LinkRevision
    {
        $revisionNumber = ((int) $link->revisions()->where('workspace_id', $link->workspace_id)->max('revision_number')) + 1;

        return $link->revisions()->create([
            'workspace_id' => $link->workspace_id,
            'actor_user_id' => $actor->id,
            'revision_number' => $revisionNumber,
            'status' => $status,
            'snapshot' => $snapshot,
        ]);
    }
}
