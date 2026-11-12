<?php

namespace App\Services\Settings;

use App\Models\CustomPage;
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
            if (($field['env'] ?? null) !== null && config((string) $field['env']) !== null) {
                unset($values[$key]);

                continue;
            }

            $rules = $field['rules'] ?? [];

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

            if (! array_key_exists($key, $values)) {
                $values[$key] = $field['default'] ?? null;
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

        return $values;
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