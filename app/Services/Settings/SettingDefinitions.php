<?php

namespace App\Services\Settings;

use App\Models\CustomPage;
use App\Rules\PlatformAssetRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingDefinitions
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        /** @var array<string, array<string, mixed>> $sections */
        $sections = config('platform-settings.sections', []);

        return $sections;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function section(string $section): ?array
    {
        return $this->sections()[$section] ?? null;
    }

    /**
     * Resolve a public settings route key to its internal section key.
     */
    public function sectionKeyForRoute(string $routeKey): ?string
    {
        foreach ($this->sections() as $sectionKey => $definition) {
            if ((string) ($definition['route_key'] ?? $sectionKey) === $routeKey) {
                return $sectionKey;
            }
        }

        return null;
    }

    public function routeKeyForSection(string $sectionKey): string
    {
        $definition = $this->section($sectionKey);

        return (string) ($definition['route_key'] ?? $sectionKey);
    }

    /**
     * @return list<string>
     */
    public function sectionKeys(): array
    {
        return array_keys($this->sections());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function fields(string $section): array
    {
        $definition = $this->section($section);

        if ($definition === null || ! is_array($definition['fields'] ?? null)) {
            return [];
        }

        /** @var array<string, array<string, mixed>> $fields */
        $fields = $definition['fields'];
        /** @var array<string, string> $controls */
        $controls = config('platform-settings.controls', []);

        foreach ($fields as &$field) {
            $type = (string) ($field['type'] ?? 'string');
            $field['control'] ??= $controls[$type] ?? 'input';
        }
        unset($field);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function validate(string $section, array $values): array
    {
        $fields = $this->fields($section);

        if ($fields === []) {
            throw ValidationException::withMessages(['section' => 'Unknown settings section.']);
        }

        $unknown = array_diff(array_keys($values), array_keys($fields));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'values' => 'Unknown setting keys: '.implode(', ', $unknown),
            ]);
        }

        foreach ($fields as $key => $field) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            if (($field['secret'] ?? false) === true
                && ($values[$key] === null || (is_string($values[$key]) && trim($values[$key]) === ''))) {
                unset($values[$key]);

                continue;
            }

            $rules = $field['rules'] ?? [];

            if (($field['asset'] ?? false) === true) {
                $rules[] = new PlatformAssetRule;
            }

            if (($field['type'] ?? null) === 'list') {
                $fullKey = 'values.'.$key;
                $validator = Validator::make(
                    ['values' => [$key => $values[$key] ?? []]],
                    [$fullKey => $rules, $fullKey.'.*' => $field['itemRules'] ?? []],
                );

                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }

                $values[$key] = array_values(array_map('strval', $values[$key] ?? []));

                continue;
            }

            if (($field['type'] ?? null) === 'int_list') {
                $fullKey = 'values.'.$key;
                $validator = Validator::make(
                    ['values' => [$key => $values[$key] ?? []]],
                    [$fullKey => $rules, $fullKey.'.*' => $field['itemRules'] ?? []],
                );

                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }

                $values[$key] = array_values(array_map('intval', $values[$key] ?? []));

                if (str_ends_with($key, '_page_ids') && $values[$key] !== []) {
                    $published = CustomPage::query()
                        ->whereIn('id', $values[$key])
                        ->where('state', 'published')
                        ->pluck('id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    if (array_diff($values[$key], $published) !== []) {
                        $validator->errors()->add($fullKey, 'Choose published custom pages only.');

                        throw new ValidationException($validator);
                    }
                }

                continue;
            }

            $fullKey = 'values.'.$key;
            $validator = Validator::make(
                ['values' => [$key => $values[$key]]],
                [$fullKey => $rules],
            );

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            if (($field['type'] ?? null) === 'bool') {
                $values[$key] = (bool) $values[$key];
            } elseif (($field['type'] ?? null) === 'int') {
                $values[$key] = (int) $values[$key];
            } elseif (($field['type'] ?? null) === 'media') {
                $values[$key] = $values[$key] === null || $values[$key] === '' ? null : (int) $values[$key];
            } elseif (($field['type'] ?? null) === 'string' || ($field['type'] ?? null) === 'email' || ($field['type'] ?? null) === 'url') {
                $values[$key] = $values[$key] === '' ? null : (string) $values[$key];
            }
        }

        if ($section === 'uploading' && isset($values['disk'])) {
            $diskName = (string) $values['disk'];
            $disk = config('filesystems.disks.'.$diskName);
            $driver = is_array($disk) ? ($disk['driver'] ?? null) : null;
            $visibility = is_array($disk) ? ($disk['visibility'] ?? null) : null;
            $root = is_array($disk) ? ($disk['root'] ?? null) : null;
            $publicRoot = is_string($root)
                && realpath($root) !== false
                && str_starts_with((string) realpath($root), (string) realpath(public_path()));

            if (! is_array($disk) || ! in_array($driver, ['local', 's3'], true) || $visibility === 'public' || $publicRoot) {
                throw ValidationException::withMessages([
                    'values.disk' => 'Choose a configured private storage disk.',
                ]);
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function assertCrossField(string $section, array $values): void
    {
        if ($section === 'email' && ($values['mailer'] ?? null) === 'smtp') {
            $errors = [];

            if (! is_string($values['smtp_host'] ?? null) || trim((string) $values['smtp_host']) === '') {
                $errors['values.smtp_host'] = 'SMTP host is required.';
            }

            $hasUsername = is_string($values['smtp_username'] ?? null) && $values['smtp_username'] !== '';
            $hasPassword = is_string($values['smtp_password'] ?? null) && $values['smtp_password'] !== '';

            if ($hasUsername !== $hasPassword) {
                $errors['values.smtp_password'] = 'SMTP username and password must be provided together.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        if ($section === 'uploading' && ($values['disk'] ?? null) === 's3') {
            $disk = config('filesystems.disks.s3');
            $disk = is_array($disk) ? $disk : [];

            if (($disk['driver'] ?? null) === 's3') {
                $errors = [];

                foreach ([
                    's3_access_key_id' => ['key', 'An access key ID is required for the s3 disk.'],
                    's3_secret_access_key' => ['secret', 'A secret access key is required for the s3 disk.'],
                    's3_region' => ['region', 'A region is required for the s3 disk.'],
                    's3_bucket' => ['bucket', 'A bucket is required for the s3 disk.'],
                ] as $setting => [$configKey, $message]) {
                    $value = $values[$setting] ?? null;

                    if (! is_string($value) || trim($value) === '') {
                        $value = $disk[$configKey] ?? null;
                    }

                    if (! is_string($value) || trim($value) === '') {
                        $errors['values.'.$setting] = $message;
                    }
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            }
        }

        if ($section === 'captcha'
            && ($values['provider'] ?? 'none') === 'turnstile'
            && (bool) ($values['on_registration'] ?? false)) {
            if (! is_string($values['site_key'] ?? null) || trim($values['site_key']) === '') {
                throw ValidationException::withMessages([
                    'values.site_key' => 'A Turnstile site key is required when registration protection is enabled.',
                ]);
            }

            if (! is_string($values['secret'] ?? null) || trim($values['secret']) === '') {
                throw ValidationException::withMessages([
                    'values.secret' => 'A Turnstile secret is required when registration protection is enabled.',
                ]);
            }
        }

        if ($section === 'system'
            && isset($values['webhook_connect_timeout_seconds'], $values['webhook_timeout_seconds'])
            && (int) $values['webhook_timeout_seconds'] < (int) $values['webhook_connect_timeout_seconds']) {
            throw ValidationException::withMessages([
                'values.webhook_timeout_seconds' => 'The response timeout must not be shorter than the connect timeout.',
            ]);
        }
    }

    public function isSecret(string $section, string $key): bool
    {
        return (bool) ($this->fields($section)[$key]['secret'] ?? false);
    }

    public function defaultValue(string $section, string $key): mixed
    {
        return $this->fields($section)[$key]['default'] ?? null;
    }
}
