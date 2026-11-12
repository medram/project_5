<?php

namespace App\Services\Settings;

use App\Enums\PlatformPermission;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Platform\AdminAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PlatformSettings
{
    private const CACHE_KEY = 'platform-settings.resolved';

    public function __construct(
        private readonly SettingDefinitions $definitions,
        private readonly AdminAudit $audit,
    ) {}

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public function navigation(): array
    {
        return array_map(
            fn (string $key, array $section): array => [
                'key' => $key,
                'label' => (string) ($section['label'] ?? $key),
                'description' => (string) ($section['description'] ?? ''),
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

        $resolved = $this->resolved();
        $rows = $this->rows($section);
        $fields = [];

        foreach ($this->definitions->fields($section) as $key => $field) {
            $env = $field['env'] ?? null;
            $environmentValue = is_string($env) ? config($env) : null;
            $isSecret = (bool) ($field['secret'] ?? false);
            $source = $environmentValue !== null ? 'environment' : 'database';
            $configured = $environmentValue !== null || isset($rows[$key]);

            $fields[] = [
                'key' => $key,
                'label' => (string) ($field['label'] ?? $key),
                'type' => (string) ($field['type'] ?? 'string'),
                'options' => $field['options'] ?? null,
                'help' => $field['help'] ?? null,
                'secret' => $isSecret,
                'readOnly' => $source === 'environment',
                'source' => $source,
                'configured' => $configured,
                'value' => $isSecret
                    ? null
                    : ($resolved[$section][$key] ?? ($field['default'] ?? null)),
            ];
        }

        return [
            'key' => $section,
            'label' => (string) ($definition['label'] ?? $section),
            'description' => (string) ($definition['description'] ?? ''),
            'fields' => $fields,
            'version' => $this->version($section),
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

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updateSection(User $actor, string $section, array $values, int $expectedVersion): array
    {
        $schema = $this->schema($section);

        if ($schema === null) {
            throw ValidationException::withMessages(['section' => 'Unknown settings section.']);
        }

        $validated = $this->definitions->validate($section, $values);

        DB::transaction(function () use ($actor, $section, $validated, $expectedVersion): void {
            $rows = PlatformSetting::query()
                ->where('section', $section)
                ->lockForUpdate()
                ->get()
                ->keyBy('key');

            $currentVersion = (int) ($rows->max('version') ?? 0);

            if ($currentVersion !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'version' => 'These settings changed in another session. Reload before saving.',
                ]);
            }

            $changed = [];

            foreach ($this->definitions->fields($section) as $key => $field) {
                if (! array_key_exists($key, $validated)) {
                    continue;
                }

                $env = $field['env'] ?? null;

                if (is_string($env) && config($env) !== null) {
                    continue;
                }

                $isSecret = (bool) ($field['secret'] ?? false);
                $value = $validated[$key];

                if ($isSecret && (is_string($value) && trim($value) === '')) {
                    continue;
                }

                $encoded = json_encode($value, JSON_THROW_ON_ERROR);

                if ($isSecret) {
                    $encoded = Crypt::encryptString((string) $encoded);
                }

                $existing = $rows->get($key);

                if ($existing instanceof PlatformSetting) {
                    $existing->forceFill([
                        'value' => $encoded,
                        'is_secret' => $isSecret,
                        'version' => $existing->version + 1,
                        'updated_by' => $actor->id,
                    ])->save();
                } else {
                    PlatformSetting::query()->create([
                        'section' => $section,
                        'key' => $key,
                        'value' => $encoded,
                        'is_secret' => $isSecret,
                        'version' => 1,
                        'updated_by' => $actor->id,
                    ]);
                }

                $changed[] = $key;
            }

            if ($changed !== []) {
                $this->audit->record(
                    $actor,
                    PlatformPermission::AdminSettingsManage,
                    'update_platform_settings',
                    null,
                    null,
                    ['section' => $section, 'keys' => $changed],
                );
            }

            Cache::forget(self::CACHE_KEY);
        });

        return $this->schema($section) ?? [];
    }

    public function applyRuntime(): void
    {
        $settings = $this->resolved();
        $general = $settings['general'] ?? [];
        $localization = $settings['localization'] ?? [];

        if (isset($general['site_name']) && is_string($general['site_name'])) {
            config(['app.name' => $general['site_name']]);
        }

        $locale = is_string($localization['default_locale'] ?? null)
            ? $localization['default_locale']
            : 'en';
        $timezone = is_string($localization['timezone'] ?? null)
            ? $localization['timezone']
            : 'UTC';

        app()->setLocale($locale);
        config(['app.locale' => $locale, 'app.timezone' => $timezone]);

        try {
            date_default_timezone_set($timezone);
        } catch (\Throwable) {
            // Invalid stored timezone is ignored in favor of the environment.
        }
    }

    public function version(string $section): int
    {
        if (! Schema::hasTable('platform_settings')) {
            return 0;
        }

        return (int) PlatformSetting::query()->where('section', $section)->max('version');
    }

    private function revision(): string
    {
        if (! Schema::hasTable('platform_settings')) {
            return 'missing';
        }

        return (string) (PlatformSetting::query()->max('updated_at') ?? 'empty');
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
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        $values = [];

        foreach ($this->definitions->sections() as $section => $definition) {
            $values[$section] = [];

            foreach ($this->definitions->fields($section) as $key => $field) {
                $values[$section][$key] = $field['default'] ?? null;
            }
        }

        if (! Schema::hasTable('platform_settings')) {
            return $values;
        }

        foreach (PlatformSetting::query()->get() as $setting) {
            $raw = $setting->value;

            if ($setting->is_secret) {
                try {
                    $raw = Crypt::decryptString((string) $raw);
                } catch (\Throwable) {
                    continue;
                }
            }

            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            if (array_key_exists($setting->section, $values)) {
                $values[$setting->section][$setting->key] = $decoded;
            }
        }

        foreach ($this->definitions->sections() as $section => $definition) {
            foreach ($this->definitions->fields($section) as $key => $field) {
                $env = $field['env'] ?? null;

                if (is_string($env) && config($env) !== null) {
                    $values[$section][$key] = config($env);
                }
            }
        }

        return $values;
    }
}