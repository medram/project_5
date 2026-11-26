<?php

namespace App\Services\Settings;

use App\Enums\PlatformPermission;
use App\Models\CustomPage;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingSection;
use App\Models\User;
use App\Services\Platform\AdminAudit;
use Illuminate\Database\QueryException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

class PlatformSettings
{
    private const CACHE_KEY = 'platform-settings.resolved';

    private ?string $appliedRevision = null;

    public function __construct(
        private readonly SettingDefinitions $definitions,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * @return list<array{key: string, routeKey: string, label: string, description: string, category: string}>
     */
    public function navigation(): array
    {
        return array_map(
            fn (string $key, array $section): array => [
                'key' => $key,
                'routeKey' => $this->definitions->routeKeyForSection($key),
                'label' => (string) ($section['label'] ?? $key),
                'description' => (string) ($section['description'] ?? ''),
                'category' => (string) ($section['category'] ?? 'platform'),
            ],
            array_keys($this->definitions->sections()),
            array_values($this->definitions->sections()),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function schema(string $section): ?array
    {
        $definition = $this->definitions->section($section);

        if ($definition === null) {
            return null;
        }

        $resolved = $this->resolved()[$section] ?? [];
        $rows = $this->rows($section);
        $fields = [];

        foreach ($this->definitions->fields($section) as $key => $field) {
            $isSecret = (bool) ($field['secret'] ?? false);
            $fields[$key] = [
                'key' => $key,
                'label' => (string) ($field['label'] ?? $key),
                'type' => (string) ($field['type'] ?? 'string'),
                'control' => (string) ($field['control'] ?? 'input'),
                'options' => $field['options'] ?? null,
                'help' => $field['help'] ?? null,
                'placeholder' => $field['placeholder'] ?? null,
                'autocomplete' => $field['autocomplete'] ?? null,
                'visibleWhen' => $field['visibleWhen'] ?? null,
                'asset' => (bool) ($field['asset'] ?? false),
                'secret' => $isSecret,
                'configured' => $isSecret
                    ? $this->secret($section, $key) !== null
                    : array_key_exists($key, $rows),
                'value' => $isSecret
                    ? null
                    : ($resolved[$key] ?? ($field['default'] ?? null)),
            ];
        }

        $groups = [];

        foreach (($definition['groups'] ?? []) as $key => $group) {
            $groupFields = array_values(array_filter(array_map(
                fn (string $field): ?array => $fields[$field] ?? null,
                $group['fields'] ?? [],
            )));

            if ($groupFields !== []) {
                $groups[] = [
                    'key' => (string) $key,
                    'label' => (string) ($group['label'] ?? $key),
                    'description' => (string) ($group['description'] ?? ''),
                    'fields' => $groupFields,
                ];
            }
        }

        if ($groups === [] && $fields !== []) {
            $groups[] = [
                'key' => 'settings',
                'label' => 'Settings',
                'description' => '',
                'fields' => array_values($fields),
            ];
        }

        return [
            'key' => $section,
            'routeKey' => $this->definitions->routeKeyForSection($section),
            'label' => (string) ($definition['label'] ?? $section),
            'description' => (string) ($definition['description'] ?? ''),
            'category' => (string) ($definition['category'] ?? 'platform'),
            'groups' => $groups,
            'version' => $this->version($section),
            'readiness' => app(PlatformSettingsReadiness::class)->forSection($section),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resolved(): array
    {
        $revision = $this->revision();
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ($cached['revision'] ?? null) === $revision) {
            /** @var array<string, array<string, mixed>> $values */
            $values = $cached['values'];

            return $values;
        }

        $values = $this->load();

        Cache::forever(self::CACHE_KEY, [
            'revision' => $revision,
            'values' => $values,
        ]);

        return $values;
    }

    public function hasStored(string $section, string $key): bool
    {
        return Schema::hasTable('platform_settings')
            && PlatformSetting::query()->where('section', $section)->where('key', $key)->exists();
    }

    public function secret(string $section, string $key): ?string
    {
        if (! $this->definitions->isSecret($section, $key)) {
            throw new InvalidArgumentException('Unknown platform setting secret.');
        }

        if (! Schema::hasTable('platform_settings')) {
            return null;
        }

        $setting = PlatformSetting::query()
            ->where('section', $section)
            ->where('key', $key)
            ->where('is_secret', true)
            ->first();

        if (! $setting instanceof PlatformSetting || $setting->value === null) {
            return null;
        }

        $decoded = json_decode(
            Crypt::decryptString($setting->value),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        $settings = $this->resolved();
        $general = $settings['general'] ?? [];
        $theme = $settings['themes'] ?? [];
        $menus = $settings['menus'] ?? [];
        $landing = $settings['landing'] ?? [];
        $seo = $settings['seo'] ?? [];
        $verification = $settings['brand-verification'] ?? [];
        $gdpr = $settings['gdpr'] ?? [];
        $analytics = $settings['analytics'] ?? [];
        $ads = $settings['ads'] ?? [];
        $captcha = $settings['captcha'] ?? [];
        $authentication = $settings['authentication'] ?? [];
        $biolinks = $settings['biolinks'] ?? [];
        $system = $settings['system'] ?? [];
        $visibility = [
            'pricing' => (bool) ($landing['pricing_enabled'] ?? false),
            'contact' => (bool) ($landing['contact_enabled'] ?? false),
            'registration' => (bool) ($landing['registration_enabled'] ?? true)
                && (bool) ($authentication['registration_enabled'] ?? true),
        ];

        $knownRoutes = [
            'home' => route('home'),
            'pricing' => route('pricing'),
            'login' => route('login'),
            'register' => route('register'),
        ];

        if (! $visibility['pricing']) {
            unset($knownRoutes['pricing']);
        }

        if (! $visibility['registration']) {
            unset($knownRoutes['register']);
        }

        if (isset($general['support_email']) && is_string($general['support_email']) && $visibility['contact']) {
            $knownRoutes['contact'] = 'mailto:'.$general['support_email'];
        }

        $pageIds = array_map('intval', array_merge(
            is_array($menus['header_page_ids'] ?? null) ? $menus['header_page_ids'] : [],
            is_array($menus['footer_page_ids'] ?? null) ? $menus['footer_page_ids'] : [],
        ));
        $pages = $pageIds === [] ? collect() : CustomPage::query()->published()->whereIn('id', $pageIds)->pluck('slug', 'id');

        $menuItems = function (mixed $items, mixed $ids) use ($knownRoutes, $pages): array {
            $result = [];
            foreach (is_array($items) ? $items : [] as $item) {
                if (is_string($item) && isset($knownRoutes[$item])) {
                    $result[] = ['label' => ucfirst($item), 'href' => $knownRoutes[$item]];
                }
            }
            foreach (is_array($ids) ? $ids : [] as $id) {
                $id = (int) $id;
                if ($pages->has($id)) {
                    $result[] = ['label' => ucfirst(str_replace('-', ' ', (string) $pages[$id])), 'href' => route('custom-pages.show', ['slug' => $pages[$id]])];
                }
            }

            return $result;
        };

        $logo = function (mixed $id): ?string {
            $id = is_numeric($id) ? (int) $id : 0;

            return $id > 0 ? route('platform.media', ['media' => $id]) : null;
        };

        return [
            'siteName' => is_string($general['site_name'] ?? null) ? $general['site_name'] : config('app.name'),
            'supportEmail' => is_string($general['support_email'] ?? null) ? $general['support_email'] : null,
            'homepage' => is_string($landing['homepage'] ?? null) ? $landing['homepage'] : 'home',
            'logos' => [
                'light' => $logo($general['logo_light_id'] ?? null),
                'dark' => $logo($general['logo_dark_id'] ?? null),
                'compact' => $logo($general['compact_logo_id'] ?? null),
                'favicon' => $logo($general['favicon_id'] ?? null),
            ],
            'theme' => [
                'scheme' => is_string($theme['default_scheme'] ?? null) ? $theme['default_scheme'] : 'system',
                'accent' => is_string($theme['accent'] ?? null) ? $theme['accent'] : 'blue',
                'radius' => is_string($theme['radius'] ?? null) ? $theme['radius'] : 'md',
            ],
            'menus' => [
                'header' => $menuItems($menus['header_items'] ?? [], $menus['header_page_ids'] ?? []),
                'footer' => $menuItems($menus['footer_items'] ?? [], $menus['footer_page_ids'] ?? []),
            ],
            'defaultDelivery' => ($settings['links']['default_delivery'] ?? 'direct') === 'splash' ? 'splash' : 'direct',
            'visibility' => $visibility,
            'capabilities' => [
                'targeting' => (bool) ($settings['links']['targeting_enabled'] ?? true),
                'pixels' => (bool) ($settings['links']['pixels_enabled'] ?? true),
                'domains' => (bool) ($settings['links']['domains_enabled'] ?? true),
            ],
            'availableBlocks' => array_values(array_filter(
                is_array($biolinks['available_blocks'] ?? null)
                    ? $biolinks['available_blocks']
                    : ['link', 'text', 'image', 'social', 'embed'],
                fn (mixed $block): bool => is_string($block) && in_array($block, ['link', 'text', 'image', 'social', 'embed'], true),
            )),
            'seo' => [
                'title' => is_string($seo['default_title'] ?? null) ? $seo['default_title'] : null,
                'description' => is_string($seo['default_description'] ?? null) ? $seo['default_description'] : null,
                'homeTitle' => is_string($seo['home_title'] ?? null) && $seo['home_title'] !== '' ? $seo['home_title'] : null,
                'homeDescription' => is_string($seo['home_description'] ?? null) && $seo['home_description'] !== '' ? $seo['home_description'] : null,
                'pricingTitle' => is_string($seo['pricing_title'] ?? null) && $seo['pricing_title'] !== '' ? $seo['pricing_title'] : null,
                'pricingDescription' => is_string($seo['pricing_description'] ?? null) && $seo['pricing_description'] !== '' ? $seo['pricing_description'] : null,
                'ogImage' => $logo($seo['og_media_id'] ?? null),
            ],
            'verification' => array_filter([
                'google' => $verification['google_token'] ?? null,
                'bing' => $verification['bing_token'] ?? null,
                'pinterest' => $verification['pinterest_token'] ?? null,
            ]),
            'gdpr' => [
                'notice' => $gdpr['consent_notice'] ?? null,
                'position' => $gdpr['consent_position'] ?? 'banner',
                'defaultDenied' => true,
                'privacyUrl' => $this->policyUrl($gdpr['privacy_page_id'] ?? null, $gdpr['privacy_url'] ?? null),
                'termsUrl' => $this->policyUrl($gdpr['terms_page_id'] ?? null, $gdpr['terms_url'] ?? null),
            ],
            'maintenanceBanner' => is_string($system['maintenance_banner'] ?? null) && trim($system['maintenance_banner']) !== ''
                ? $system['maintenance_banner']
                : null,
            'analytics' => [
                'consentEnabled' => (bool) ($analytics['consent_enabled'] ?? true),
                'ga4' => $analytics['ga4_measurement_id'] ?? null,
            ],
            'captcha' => ($captcha['provider'] ?? 'none') === 'turnstile'
                && (bool) ($captcha['on_registration'] ?? false)
                && is_string($captcha['site_key'] ?? null)
                && $captcha['site_key'] !== '' ? [
                    'enabled' => true,
                    'siteKey' => $captcha['site_key'],
                ] : ['enabled' => false, 'siteKey' => null],
            'ads' => ($ads['provider'] ?? 'none') === 'adsense' ? [
                'provider' => 'adsense',
                'publisherId' => $ads['publisher_id'] ?? null,
                'headerSlot' => $ads['header_slot'] ?? null,
                'contentSlot' => $ads['content_slot'] ?? null,
                'consentRequired' => (bool) ($ads['consent_required'] ?? true),
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updateSection(User $actor, string $section, array $values, int $expectedVersion): array
    {
        if ($this->definitions->section($section) === null) {
            throw ValidationException::withMessages(['section' => 'Unknown settings section.']);
        }

        $validated = $this->definitions->validate($section, $values);

        DB::transaction(function () use ($actor, $section, $validated, $expectedVersion): void {
            $sectionState = $this->lockSection($section, $expectedVersion);
            $rows = PlatformSetting::query()
                ->where('section', $section)
                ->lockForUpdate()
                ->get()
                ->keyBy('key');
            if ($section === 'subscriptions') {
                $current = $this->effectiveValues($section, [], $rows);
                foreach (['paypal_mode', 'paypal_client_id'] as $key) {
                    if (array_key_exists($key, $validated) && $validated[$key] !== $current[$key]
                        && $this->hasLivePayPalSubscriptions()) {
                        throw ValidationException::withMessages([
                            'values.'.$key => 'Cancel existing PayPal subscriptions and checkouts before changing the PayPal account.',
                        ]);
                    }
                }
            }
            $this->definitions->assertCrossField(
                $section,
                $this->effectiveValues($section, $validated, $rows),
            );
            $changed = [];

            foreach ($validated as $key => $value) {
                $field = $this->definitions->fields($section)[$key];
                $isSecret = (bool) ($field['secret'] ?? false);

                if ($isSecret && is_string($value) && trim($value) === '') {
                    continue;
                }

                $existing = $rows->get($key);
                $current = $existing instanceof PlatformSetting
                    ? $this->decode($existing)
                    : ($field['default'] ?? null);

                if ($current === $value && (! $existing instanceof PlatformSetting || $existing->is_secret === $isSecret)) {
                    continue;
                }

                $attributes = [
                    'value' => $this->encode($value, $isSecret),
                    'is_secret' => $isSecret,
                    'updated_by' => $actor->id,
                ];

                if ($existing instanceof PlatformSetting) {
                    $existing->forceFill($attributes)->save();
                } else {
                    PlatformSetting::query()->create([
                        'section' => $section,
                        'key' => $key,
                        ...$attributes,
                    ]);
                }

                $changed[] = $key;
            }

            if ($changed === []) {
                return;
            }

            $sectionState->increment('revision');
            $this->audit->record(
                $actor,
                PlatformPermission::AdminSettingsManage,
                'update_platform_settings',
                null,
                null,
                ['section' => $section, 'keys' => $changed],
            );
            Cache::forget(self::CACHE_KEY);
        });

        return $this->schema($section) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function clearSecret(
        User $actor,
        string $section,
        string $key,
        int $expectedVersion,
    ): array {
        if (! $this->definitions->isSecret($section, $key)) {
            throw ValidationException::withMessages(['key' => 'Unknown secret setting.']);
        }

        DB::transaction(function () use ($actor, $section, $key, $expectedVersion): void {
            $sectionState = $this->lockSection($section, $expectedVersion);
            $setting = PlatformSetting::query()
                ->where('section', $section)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if (! $setting instanceof PlatformSetting || $this->decode($setting) === null) {
                return;
            }
            if ($section === 'subscriptions' && $key === 'paypal_secret' && $this->hasLivePayPalSubscriptions()) {
                throw ValidationException::withMessages(['key' => 'Cancel existing PayPal subscriptions and checkouts before removing this secret.']);
            }

            $setting->forceFill([
                'value' => $this->encode(null, true),
                'is_secret' => true,
                'updated_by' => $actor->id,
            ])->save();
            $sectionState->increment('revision');
            $this->audit->record(
                $actor,
                PlatformPermission::AdminSettingsManage,
                'clear_platform_setting_secret',
                null,
                null,
                ['section' => $section, 'key' => $key],
            );
            Cache::forget(self::CACHE_KEY);
        });

        return $this->schema($section) ?? [];
    }

    /**
     * Write only missing rows for an import. Existing administrator values are never overwritten.
     *
     * @param  array<string, mixed>  $values
     * @return array{imported: list<string>, skipped: list<string>}
     */
    public function importSection(string $section, array $values): array
    {
        if ($this->definitions->section($section) === null) {
            throw ValidationException::withMessages(['section' => 'Unknown settings section.']);
        }

        $validated = $this->definitions->validate($section, $values);
        $this->definitions->assertCrossField($section, $validated);
        $result = ['imported' => [], 'skipped' => []];

        DB::transaction(function () use ($section, $validated, &$result): void {
            $state = PlatformSettingSection::query()
                ->whereKey($section)
                ->lockForUpdate()
                ->first();

            if (! $state instanceof PlatformSettingSection) {
                throw ValidationException::withMessages(['section' => 'Unknown settings section.']);
            }

            $existing = PlatformSetting::query()
                ->where('section', $section)
                ->lockForUpdate()
                ->pluck('key')
                ->all();

            foreach ($validated as $key => $value) {
                if (in_array($key, $existing, true)) {
                    $result['skipped'][] = $key;

                    continue;
                }

                $field = $this->definitions->fields($section)[$key];
                $isSecret = (bool) ($field['secret'] ?? false);

                if ($isSecret && $value === null) {
                    $result['skipped'][] = $key;

                    continue;
                }

                PlatformSetting::query()->create([
                    'section' => $section,
                    'key' => $key,
                    'value' => $this->encode($value, $isSecret),
                    'is_secret' => $isSecret,
                    'updated_by' => null,
                ]);
                $result['imported'][] = $key;
            }

            if ($result['imported'] !== []) {
                $state->increment('revision');
                Cache::forget(self::CACHE_KEY);
            }
        });

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function updateAsset(
        User $actor,
        string $section,
        string $key,
        ?int $mediaId,
        int $expectedVersion,
    ): array {
        $field = $this->definitions->fields($section)[$key] ?? null;

        if (($field['asset'] ?? false) !== true) {
            throw ValidationException::withMessages(['key' => 'Unknown platform asset setting.']);
        }

        $this->definitions->validate($section, [$key => $mediaId]);

        DB::transaction(function () use ($actor, $section, $key, $mediaId, $expectedVersion): void {
            $sectionState = $this->lockSection($section, $expectedVersion);
            $row = PlatformSetting::query()
                ->where('section', $section)
                ->where('key', $key)
                ->lockForUpdate()
                ->first();
            $current = $row instanceof PlatformSetting ? $this->decode($row) : null;
            $currentId = is_int($current) ? $current : null;

            if ($currentId === $mediaId) {
                return;
            }

            $attributes = [
                'value' => $this->encode($mediaId, false),
                'is_secret' => false,
                'updated_by' => $actor->id,
            ];

            if ($row instanceof PlatformSetting) {
                $row->forceFill($attributes)->save();
            } else {
                PlatformSetting::query()->create([
                    'section' => $section,
                    'key' => $key,
                    ...$attributes,
                ]);
            }

            $sectionState->increment('revision');
            $this->audit->record(
                $actor,
                PlatformPermission::AdminSettingsManage,
                'update_platform_setting_asset',
                null,
                null,
                ['section' => $section, 'key' => $key],
            );
            Cache::forget(self::CACHE_KEY);
        });

        return $this->schema($section) ?? [];
    }

    public function assetReferenced(int $mediaId): bool
    {
        if (CustomPage::query()->where('og_media_id', $mediaId)->exists()) {
            return true;
        }

        foreach (PlatformSetting::query()
            ->whereIn('section', ['general', 'biolinks', 'seo'])
            ->whereIn('key', ['logo_light_id', 'logo_dark_id', 'compact_logo_id', 'favicon_id', 'branding_media_id', 'og_media_id'])
            ->where('is_secret', false)
            ->pluck('value') as $value) {
            if ((int) json_decode((string) $value, true) === $mediaId) {
                return true;
            }
        }

        return false;
    }

    public function applyRuntime(): void
    {
        if (! $this->initialized()) {
            return;
        }

        $revision = $this->revision();

        if ($this->appliedRevision === $revision) {
            return;
        }

        $settings = $this->resolved();
        $general = $settings['general'] ?? [];
        $localization = $settings['localization'] ?? [];
        $email = $settings['email'] ?? [];
        $subscriptions = $settings['subscriptions'] ?? [];
        $authentication = $settings['authentication'] ?? [];
        $analytics = $settings['analytics'] ?? [];
        $gdpr = $settings['gdpr'] ?? [];
        $uploading = $settings['uploading'] ?? [];
        $system = $settings['system'] ?? [];

        config([
            'app.name' => is_string($general['site_name'] ?? null) ? $general['site_name'] : 'LinkPilot',
            'app.locale' => is_string($localization['default_locale'] ?? null) ? $localization['default_locale'] : 'en',
            'app.timezone' => is_string($localization['timezone'] ?? null) ? $localization['timezone'] : 'UTC',
        ]);
        app()->setLocale((string) config('app.locale'));

        try {
            date_default_timezone_set((string) config('app.timezone'));
        } catch (\Throwable) {
            // An invalid stored timezone must not replace the deployment timezone default.
        }

        $mailer = ($email['mailer'] ?? 'log') === 'smtp' ? 'smtp' : 'log';
        config([
            'mail.default' => $mailer,
            'mail.from.name' => is_string($email['sender_name'] ?? null) ? $email['sender_name'] : 'LinkPilot',
            'mail.from.address' => is_string($email['sender_address'] ?? null) ? $email['sender_address'] : 'noreply@example.test',
        ]);

        if ($mailer === 'smtp') {
            $security = in_array($email['smtp_security'] ?? null, ['starttls', 'tls', 'none'], true)
                ? $email['smtp_security']
                : 'starttls';
            config(['mail.mailers.smtp' => array_merge(
                (array) config('mail.mailers.smtp', []),
                [
                    'transport' => 'smtp',
                    'scheme' => $security === 'tls' ? 'smtps' : 'smtp',
                    'host' => $email['smtp_host'] ?? null,
                    'port' => (int) ($email['smtp_port'] ?? 587),
                    'username' => $email['smtp_username'] ?? null,
                    'password' => $this->secret('email', 'smtp_password'),
                    'timeout' => (int) ($email['smtp_timeout_seconds'] ?? 60),
                    'auto_tls' => $security === 'starttls',
                    'verify_peer' => true,
                    'local_domain' => filled($email['smtp_ehlo_domain'] ?? null)
                        ? $email['smtp_ehlo_domain']
                        : parse_url((string) config('app.url'), PHP_URL_HOST),
                ],
            )]);
        }

        config([
            'cashier.key' => $subscriptions['stripe_publishable_key'] ?? null,
            'cashier.secret' => $this->secret('subscriptions', 'stripe_secret'),
            'cashier.webhook.secret' => $this->secret('subscriptions', 'stripe_webhook_secret'),
            'cashier.currency' => $subscriptions['currency'] ?? 'usd',
            'cashier.tax_policy_acknowledged' => (bool) ($subscriptions['tax_policy_acknowledged'] ?? false),
            'services.paypal.mode' => $subscriptions['paypal_mode'] ?? 'sandbox',
            'services.paypal.client_id' => $subscriptions['paypal_client_id'] ?? null,
            'services.paypal.secret' => $this->secret('subscriptions', 'paypal_secret'),
            'services.paypal.webhook_id' => $subscriptions['paypal_webhook_id'] ?? null,
            'linkpilot.billing.promotion_codes' => (bool) ($subscriptions['promotion_codes'] ?? false),
            'services.google.client_id' => $authentication['google_client_id'] ?? null,
            'services.google.client_secret' => $this->secret('authentication', 'google_client_secret'),
            'linkpilot.auth.google_enabled' => (bool) ($authentication['google_enabled'] ?? false),
            'services.linkpilot.analytics.privacy_url' => $this->policyUrl($gdpr['privacy_page_id'] ?? null, $gdpr['privacy_url'] ?? null),
            'services.linkpilot.analytics.hash_key' => $this->secret('analytics', 'visitor_hash_key'),
            'services.linkpilot.analytics.hash_key_version' => $analytics['visitor_hash_version'] ?? null,
            'services.linkpilot.analytics.retention_days' => (int) ($analytics['retention_days'] ?? 90),
            'services.linkpilot.media.retention_days' => (int) ($uploading['retention_days'] ?? 365),
            'services.linkpilot.webhooks.retry_delays' => $system['webhook_retry_delays'] ?? [60, 300],
            'services.linkpilot.webhooks.connect_timeout' => (int) ($system['webhook_connect_timeout_seconds'] ?? 4),
            'services.linkpilot.webhooks.timeout' => (int) ($system['webhook_timeout_seconds'] ?? 30),
            'services.linkpilot.webhooks.max_attempts' => (int) ($system['webhook_max_attempts'] ?? 3),
            'services.linkpilot.webhooks.excerpt_bytes' => (int) ($system['webhook_excerpt_bytes'] ?? 500),
            'linkpilot.api.rate_limit_per_minute' => (int) ($system['api_rate_limit_per_minute'] ?? 60),
        ]);

        $storedUploading = $this->storedKeys('uploading');
        $s3Overrides = [];

        foreach ([
            's3_access_key_id' => 'key',
            's3_region' => 'region',
            's3_bucket' => 'bucket',
            's3_endpoint' => 'endpoint',
        ] as $setting => $configKey) {
            if (! isset($storedUploading[$setting])) {
                continue;
            }

            $value = $uploading[$setting] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $s3Overrides[$configKey] = $value;
            }
        }

        if (isset($storedUploading['s3_secret_access_key'])) {
            $secret = $this->secret('uploading', 's3_secret_access_key');

            if ($secret !== null) {
                $s3Overrides['secret'] = $secret;
            }
        }

        if (isset($storedUploading['s3_use_path_style_endpoint'])) {
            $s3Overrides['use_path_style_endpoint'] = (bool) ($uploading['s3_use_path_style_endpoint'] ?? false);
        }

        if ($s3Overrides !== []) {
            $s3 = config('filesystems.disks.s3');
            config(['filesystems.disks.s3' => array_merge(
                is_array($s3) ? $s3 : ['driver' => 's3', 'throw' => false, 'report' => false],
                $s3Overrides,
            )]);
            Storage::forgetDisk('s3');
        }

        if (isset($storedUploading['disk']) || ! is_string(config('services.linkpilot.media.disk')) || trim((string) config('services.linkpilot.media.disk')) === '') {
            config(['services.linkpilot.media.disk' => $uploading['disk'] ?? null]);
        }

        $this->appliedRevision = $revision;

        app(MailManager::class)->forgetMailers();
        $socialite = app(SocialiteFactory::class);

        if (method_exists($socialite, 'forgetDrivers')) {
            $socialite->forgetDrivers();
        }
    }

    private function policyUrl(mixed $pageId, mixed $url): ?string
    {
        if (is_numeric($pageId)) {
            $slug = CustomPage::query()
                ->published()
                ->whereKey((int) $pageId)
                ->value('slug');

            if (is_string($slug) && $slug !== '') {
                return route('custom-pages.show', ['slug' => $slug]);
            }
        }

        return is_string($url) && trim($url) !== '' ? $url : null;
    }

    private function initialized(): bool
    {
        try {
            return Schema::hasTable('platform_setting_sections')
                && Schema::hasTable('platform_settings');
        } catch (QueryException) {
            return false;
        }
    }

    public function version(string $section): int
    {
        if (! Schema::hasTable('platform_setting_sections')) {
            return 0;
        }

        return (int) (PlatformSettingSection::query()->find($section)?->revision ?? 0);
    }

    public function revision(): string
    {
        if (! Schema::hasTable('platform_setting_sections')) {
            return 'missing';
        }

        $revisions = PlatformSettingSection::query()
            ->orderBy('section')
            ->pluck('revision', 'section')
            ->map(fn (int $revision, string $section): string => $section.':'.$revision)
            ->implode('|');

        return hash('sha256', $revisions);
    }

    /**
     * @return array<string, PlatformSetting>
     */
    private function rows(string $section): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return [];
        }

        return PlatformSetting::query()
            ->where('section', $section)
            ->get()
            ->keyBy('key')
            ->all();
    }

    /**
     * @return array<string, true>
     */
    private function storedKeys(string $section): array
    {
        if (! Schema::hasTable('platform_settings')) {
            return [];
        }

        return array_fill_keys(
            PlatformSetting::query()
                ->where('section', $section)
                ->pluck('key')
                ->map(fn (mixed $key): string => (string) $key)
                ->all(),
            true,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        $values = [];

        foreach ($this->definitions->sections() as $section => $definition) {
            $values[$section] = [];

            foreach ($this->definitions->fields($section) as $key => $field) {
                if (! (bool) ($field['secret'] ?? false)) {
                    $values[$section][$key] = $field['default'] ?? null;
                }
            }
        }

        if (! Schema::hasTable('platform_settings')) {
            return $values;
        }

        foreach (PlatformSetting::query()->where('is_secret', false)->get() as $setting) {
            if (
                array_key_exists($setting->section, $values)
                && ! $this->definitions->isSecret($setting->section, $setting->key)
            ) {
                $values[$setting->section][$setting->key] = $this->decode($setting);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  Collection<string, PlatformSetting>  $rows
     * @return array<string, mixed>
     */
    private function effectiveValues(string $section, array $changed, $rows): array
    {
        $effective = [];

        foreach ($this->definitions->fields($section) as $key => $field) {
            if (array_key_exists($key, $changed)) {
                $effective[$key] = $changed[$key];
            } elseif ($rows->has($key)) {
                $effective[$key] = $this->decode($rows->get($key));
            } else {
                $effective[$key] = $field['default'] ?? null;
            }
        }

        return $effective;
    }

    private function hasLivePayPalSubscriptions(): bool
    {
        return Schema::hasTable('paypal_subscriptions')
            && DB::table('paypal_subscriptions')
                ->whereIn('status', ['CREATING', 'APPROVAL_PENDING', 'APPROVED', 'ACTIVE', 'SUSPENDED', 'PAYMENT_FAILED'])
                ->exists();
    }

    private function lockSection(string $section, int $expectedVersion): PlatformSettingSection
    {
        $state = PlatformSettingSection::query()
            ->whereKey($section)
            ->lockForUpdate()
            ->first();

        if (! $state instanceof PlatformSettingSection) {
            throw ValidationException::withMessages(['section' => 'Unknown settings section.']);
        }

        if ($state->revision !== $expectedVersion) {
            throw ValidationException::withMessages([
                'version' => 'These settings changed in another session. Reload before saving.',
            ]);
        }

        return $state;
    }

    private function encode(mixed $value, bool $secret): string
    {
        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        return $secret ? Crypt::encryptString($encoded) : $encoded;
    }

    private function decode(PlatformSetting $setting): mixed
    {
        $encoded = $setting->value;

        if ($setting->is_secret && $encoded !== null) {
            $encoded = Crypt::decryptString($encoded);
        }

        return $encoded === null
            ? null
            : json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
    }
}
