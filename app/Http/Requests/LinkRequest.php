<?php

namespace App\Http\Requests;

use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkTag;
use App\Services\Links\FirstPartyDestinationGuard;
use App\Services\Links\LinkPolicy;
use App\Services\Settings\PlatformSettings;
use App\Services\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Url;

abstract class LinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $slug = strtolower(trim($this->input('slug')));
            $this->merge(['slug' => $slug === '' ? null : $slug]);
        }

        $routingRules = $this->input('routing_rules');
        if (is_array($routingRules)) {
            foreach ($routingRules as $index => $rule) {
                if (! is_array($rule) || ! is_string($rule['match_value'] ?? null)) {
                    continue;
                }

                $routingRules[$index]['match_value'] = ($rule['rule_type'] ?? null) === 'country'
                    ? strtoupper(trim($rule['match_value']))
                    : strtolower(trim($rule['match_value']));
            }
            $this->merge(['routing_rules' => $routingRules]);
        }

        if (! $this->has('delivery_mode')) {
            $routeLink = $this->route('link');
            $settings = app(PlatformSettings::class)->resolved();
            $this->merge([
                'delivery_mode' => $routeLink instanceof Link
                    ? $routeLink->delivery_mode
                    : ($this->filled('link_overlay_id') ? 'overlay' : ($settings['links']['default_delivery'] ?? 'direct')),
            ]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    protected function linkRules(?Link $ignoredLink = null, bool $slugRequired = false): array
    {
        $workspace = app(WorkspaceContext::class)->currentFor($this->user());
        $settings = app(PlatformSettings::class)->resolved();
        $slugMin = max(2, (int) ($settings['links']['slug_min'] ?? 4));
        $slugMax = max($slugMin, min(64, (int) ($settings['links']['slug_max'] ?? 32)));
        $slugPattern = ($settings['links']['slug_charset'] ?? 'alphanumeric_dash') === 'alphanumeric'
            ? '/\\A[a-z0-9]{'.$slugMin.','.$slugMax.'}\\z/'
            : '/\\A[a-z0-9-]{'.$slugMin.','.$slugMax.'}\\z/';
        $uniqueSlug = Rule::unique(Link::class, 'slug');
        if ($ignoredLink !== null) {
            $uniqueSlug->ignore($ignoredLink);
        }

        return [
            'delivery_mode' => ['required', 'in:direct,splash,link_page,overlay'],
            'destination_url' => [
                'required_unless:delivery_mode,link_page',
                'nullable',
                'string',
                'max:2048',
                (new Url)->protocols(['http', 'https']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if (app(FirstPartyDestinationGuard::class)->rejects($value)) {
                        $fail('The destination URL must not target this application.');

                        return;
                    }

                    $rejection = app(LinkPolicy::class)->rejection(
                        $value,
                        is_string($this->input('slug')) ? $this->input('slug') : null,
                        is_string($this->input('title')) ? $this->input('title') : null,
                    );

                    if ($rejection !== null) {
                        $fail($rejection);
                    }
                },
            ],
            'title' => ['nullable', 'string', 'max:160'],
            'slug' => [
                $slugRequired ? 'required' : 'nullable', 'string', 'max:'.$slugMax,
                'regex:'.$slugPattern,
                Rule::notIn((array) config('linkpilot.reserved_slugs', [])), $uniqueSlug,
            ],
            'is_active' => ['required', 'boolean'],
            'tag_ids' => ['sometimes', 'array', 'max:10'],
            'tag_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(LinkTag::class, 'id')->where('workspace_id', $workspace->id),
            ],
            'folder_id' => ['nullable', 'integer', Rule::exists(Folder::class, 'id')->where('workspace_id', $workspace->getKey())],
            'custom_domain_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($workspace, $settings): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (! (bool) ($settings['links']['domains_enabled'] ?? true)) {
                    $link = $this->route('link');

                    if (! $link instanceof Link || (int) $link->custom_domain_id !== (int) $value) {
                        $fail('Custom domains are disabled by platform policy.');
                    }

                    return;
                }
                Gate::authorize('selectIntegrations', $workspace);
                /** @var CustomDomain|null $domain */
                $domain = $workspace->customDomains()->whereKey($value)->first();
                $link = $this->route('link');
                if ($domain === null || ($domain->status !== 'active' && ! ($link instanceof Link && $link->custom_domain_id === $domain->id))) {
                    $fail('Only an active custom domain in this workspace can be assigned to a link.');
                }
            }],
            'access_code' => ['nullable', 'string', 'max:255'],
            'clear_access_code' => ['nullable', 'boolean'],
            'link_overlay_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                if ($value === null || $value === '') {
                    if ($this->input('delivery_mode') === 'overlay') {
                        $fail('Choose an active overlay for overlay delivery.');
                    }

                    return;
                }
                if ($this->input('delivery_mode') !== 'overlay') {
                    $fail('An overlay can only be assigned to overlay delivery.');

                    return;
                }
                Gate::authorize('selectIntegrations', $workspace);
                /** @var LinkOverlay|null $overlay */
                $overlay = $workspace->linkOverlays()->whereKey($value)->first();
                $link = $this->route('link');
                if ($overlay === null || (! $overlay->is_active && ! ($link instanceof Link && $link->link_overlay_id === $overlay->id))) {
                    $fail('Only an active overlay in this workspace can be assigned to a link.');
                }
            }],
            'link_page_id' => ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                if ($value === null || $value === '') {
                    if ($this->input('delivery_mode') === 'link_page') {
                        $fail('Choose a published link page in this workspace.');
                    }

                    return;
                }

                if ($this->input('delivery_mode') !== 'link_page') {
                    $fail('A link page can only be assigned to link-page delivery.');

                    return;
                }
                Gate::authorize('selectIntegrations', $workspace);
                $page = $workspace->linkPages()->whereKey($value)->first();
                if ($page === null || ! $page->is_published) {
                    $fail('Choose a published link page in this workspace for link-page delivery.');
                }
            }],
            'max_clicks' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'approval_required' => ['sometimes', 'boolean', function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                $link = $this->route('link');
                if ($link instanceof Link && (bool) $value === (bool) $link->approval_required) {
                    return;
                }
                if (! (bool) $value && ! ($link instanceof Link && $link->approval_required)) {
                    return;
                }

                Gate::authorize('manageLinkApprovals', $workspace);
            }],

            'utm_parameters' => ['nullable', 'array:utm_source,utm_medium,utm_campaign,utm_term,utm_content'],
            'utm_parameters.*' => ['nullable', 'string', 'max:512'],
            'custom_parameters' => ['nullable', 'array', 'max:10', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                foreach (array_keys($value) as $key) {
                    if (! is_string($key) || preg_match('/\\A[a-zA-Z][a-zA-Z0-9_.-]{0,63}\\z/', $key) !== 1) {
                        $fail('Custom parameter names must use letters, digits, dots, underscores, or hyphens.');

                        return;
                    }
                }
            }],
            'custom_parameters.*' => ['nullable', 'string', 'max:512'],
            'routing_rules' => ['sometimes', 'array', 'max:25', function (string $attribute, mixed $value, Closure $fail) use ($settings): void {
                if (is_array($value) && $value !== [] && ! (bool) ($settings['links']['targeting_enabled'] ?? true)) {
                    $fail('Targeting rules are disabled by platform policy.');
                }
            }],
            'routing_rules.*' => ['required', 'array:rule_type,match_value,target_url'],
            'routing_rules.*.rule_type' => ['required', 'in:country,device,platform'],
            'routing_rules.*.match_value' => ['required', 'string', 'max:16', function (string $attribute, mixed $value, Closure $fail): void {
                $index = explode('.', $attribute)[1] ?? null;
                $type = is_string($index) ? data_get($this->input('routing_rules'), $index.'.rule_type') : null;
                $valid = match ($type) {
                    'country' => is_string($value) && preg_match('/\\A[A-Z]{2}\\z/', $value) === 1 && ! in_array($value, ['XX', 'T1'], true),
                    'device' => in_array($value, ['mobile', 'tablet', 'desktop'], true),
                    'platform' => in_array($value, ['android', 'ios', 'chromeos', 'windows', 'macos', 'linux'], true),
                    default => false,
                };

                if (! $valid) {
                    $fail('Choose a supported country, device, or platform match value.');
                }
            }],
            'routing_rules.*.target_url' => [
                'required',
                'string',
                'max:2048',
                (new Url)->protocols(['http', 'https']),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && app(FirstPartyDestinationGuard::class)->rejects($value)) {
                        $fail('A routing target must not target this application.');

                        return;
                    }

                    if (is_string($value)) {
                        $rejection = app(LinkPolicy::class)->rejection(
                            $value,
                            is_string($this->input('slug')) ? $this->input('slug') : null,
                            is_string($this->input('title')) ? $this->input('title') : null,
                        );

                        if ($rejection !== null) {
                            $fail($rejection);
                        }
                    }
                },
            ],
            'available_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:available_from'],
        ];
    }
}
