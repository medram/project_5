<?php

namespace App\Services\Platform;

use App\Http\Middleware\ScopedAdminContent;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminContentMutation
{
    private const ROLLBACK_CALLBACKS = 'admin_content_rollback_callbacks';

    private const FIELDS = [
        'name', 'title', 'slug', 'handle', 'display_name', 'bio', 'description', 'appearance',
        'is_active', 'is_published', 'destination_url', 'delivery_mode', 'folder_id',
        'custom_domain_id', 'link_overlay_id', 'link_page_id', 'link_id', 'tag_ids', 'routing_rules',
        'approval_required', 'access_code', 'remove_access_code', 'clear_access_code', 'available_from',
        'expires_at', 'max_scans', 'max_clicks', 'foreground_color', 'background_color', 'error_correction',
        'margin', 'size', 'logo_alt', 'clear_logo', 'share_title', 'share_description', 'hostname', 'status',
        'provider', 'provider_id', 'event', 'trigger', 'target_type', 'target_id', 'type', 'content', 'position', 'items',
        'url', 'label', 'text', 'alt_text', 'identifier', 'provider_type', 'embed_url', 'image_url',
        'link_page_variant_id', 'variant_id', 'template', 'weight', 'is_default', 'fields',
        'starts_at', 'ends_at', 'cta_label', 'cta_url', 'heading', 'body', 'settings',
        'utm_parameters', 'custom_parameters', 'display_delay_seconds',
    ];

    public function __construct(
        private readonly PlatformAuthorization $authorization,
        private readonly AdminAudit $audit,
    ) {}

    public function run(Request $request, Model $root, string $action, Closure $mutate): mixed
    {
        $scope = $request->attributes->get(ScopedAdminContent::ATTRIBUTE);
        abort_unless(is_array($scope) && $scope['root']->is($root), 403);
        $level = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $this->authorization->lockSuperAdmin();
            $actor = User::query()->whereKey($request->user()->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($this->authorization->allows($actor, $scope['permission']), 403);
            $lockedRoot = $root->newQuery()->whereKey($root->getKey())->lockForUpdate()->firstOrFail();
            abort_unless((int) $lockedRoot->getAttribute('workspace_id') === (int) $scope['workspace']->getKey(), 404);
            $before = $this->capture($request, $lockedRoot);

            $result = $mutate();
            if ($result instanceof Response && ($result->getStatusCode() >= 400 || ($result->exception ?? null) !== null)) {
                DB::rollBack($level);
                $this->cleanup($request);

                return $result;
            }

            $fields = $this->changedFields($request, $before);
            $this->audit->record($actor, $scope['permission'], $action, $root, null, [
                'workspace_id' => $scope['workspace']->getKey(),
                'fields' => $fields,
            ]);
            DB::commit();
            $request->attributes->remove(self::ROLLBACK_CALLBACKS);

            return $result;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > $level) {
                DB::rollBack($level);
                $this->cleanup($request);
            } else {
                $request->attributes->remove(self::ROLLBACK_CALLBACKS);
            }
            throw $exception;
        }
    }

    private function capture(Request $request, Model $root): array
    {
        $models = [$root];
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model && ! $parameter->is($root)) {
                $models[] = $parameter;
            }
        }

        $relation = match ((string) $request->route()->getName()) {
            'admin.content.biolinks.links.store' => 'bioLinks',
            'admin.content.biolinks.blocks.store' => 'contentBlocks',
            'admin.content.link-pages.items.store' => 'items',
            'admin.content.link-pages.blocks.store' => 'blocks',
            'admin.content.link-pages.variants.store' => 'variants',
            'admin.content.pixels.rules.store' => 'rules',
            default => null,
        };
        $before = [
            'models' => array_map(fn (Model $model) => [$model, $model->getAttributes()], $models),
            'root' => $root,
            'relation' => $relation,
            'relatedIds' => $relation === null ? [] : $root->{$relation}()->pluck('id')->all(),
        ];
        if ($root instanceof Link) {
            $before['tags'] = $root->tags()->orderBy('link_tags.id')->pluck('link_tags.id')->all();
            $before['rules'] = $root->routingRules()->orderBy('position')->get(['rule_type', 'match_value', 'target_url'])->toArray();
            $before['revisionId'] = (int) ($root->revisions()->max('id') ?? 0);
        }
        if ($request->routeIs('admin.content.biolinks.content.reorder', 'admin.content.link-pages.items.reorder')) {
            $before['order'] = $this->contentOrder($root);
        }

        return $before;
    }

    private function changedFields(Request $request, array $before): array
    {
        $fields = [];
        foreach ($before['models'] as [$model, $attributes]) {
            $after = $model->newQuery()->find($model->getKey())?->getAttributes() ?? [];
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $attributes) && array_key_exists($field, $after)
                    && $attributes[$field] !== $after[$field]) {
                    $fields[$field] = true;
                }
            }
        }

        $root = $before['root'];
        if ($before['relation'] !== null) {
            $relation = $before['relation'];
            foreach ($root->{$relation}()->whereNotIn('id', $before['relatedIds'])->get() as $created) {
                foreach (array_intersect(array_keys($created->getAttributes()), self::FIELDS) as $field) {
                    $fields[$field] = true;
                }
            }
        }
        if (isset($before['order']) && $before['order'] !== $this->contentOrder($root)) {
            $fields['position'] = true;
        }
        if ($root instanceof Link) {
            if ($before['tags'] !== $root->tags()->orderBy('link_tags.id')->pluck('link_tags.id')->all()) {
                $fields['tag_ids'] = true;
            }
            if ($before['rules'] !== $root->routingRules()->orderBy('position')->get(['rule_type', 'match_value', 'target_url'])->toArray()) {
                $fields['routing_rules'] = true;
            }
            $pending = $root->revisions()->where('id', '>', $before['revisionId'])
                ->where('status', 'pending')->orderByDesc('id')->first();
            if ($pending !== null) {
                foreach (self::FIELDS as $field) {
                    if (array_key_exists($field, $pending->snapshot) && array_key_exists($field, $root->getAttributes())
                        && $root->getAttribute($field) != $pending->snapshot[$field]) {
                        $fields[$field] = true;
                    }
                }
                if ($before['tags'] != ($pending->snapshot['tag_ids'] ?? [])) {
                    $fields['tag_ids'] = true;
                }
                if ($before['rules'] != ($pending->snapshot['routing_rules'] ?? [])) {
                    $fields['routing_rules'] = true;
                }
            }
        }

        return array_keys($fields);
    }

    private function contentOrder(Model $root): array
    {
        if ($root instanceof BioPage) {
            return [
                $root->bioLinks()->orderBy('id')->pluck('position', 'id')->all(),
                $root->contentBlocks()->orderBy('id')->pluck('position', 'id')->all(),
            ];
        }
        if ($root instanceof LinkPage) {
            return [
                $root->items()->orderBy('id')->pluck('position', 'id')->all(),
                $root->blocks()->orderBy('id')->pluck('position', 'id')->all(),
            ];
        }

        return [];
    }

    public function onRollback(Request $request, Closure $cleanup): void
    {
        abort_unless(is_array($request->attributes->get(ScopedAdminContent::ATTRIBUTE)), 403);
        $base = request();
        $callbacks = $base->attributes->get(self::ROLLBACK_CALLBACKS, []);
        $callbacks[] = $cleanup;
        $base->attributes->set(self::ROLLBACK_CALLBACKS, $callbacks);
    }

    private function cleanup(Request $request): void
    {
        foreach ($request->attributes->get(self::ROLLBACK_CALLBACKS, []) as $cleanup) {
            try {
                $cleanup();
            } catch (Throwable $exception) {
                report($exception);
            }
        }
        $request->attributes->remove(self::ROLLBACK_CALLBACKS);
    }
}
