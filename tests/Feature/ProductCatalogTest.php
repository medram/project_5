<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    private const FEATURE_IDS = [
        'auth-account',
        'dashboard-shell',
        'short-links',
        'folders',
        'qr-codes',
        'bio-pages',
        'analytics',
        'custom-domains',
        'overlays',
        'tracking-pixels',
        'link-pages',
        'workspaces',
        'billing',
        'api-keys',
        'webhooks',
        'notifications',
        'admin-insights',
        'admin-plans-subscriptions',
        'admin-users-roles',
        'admin-content',
        'admin-settings',
        'admin-files-pages-localization-logs',
    ];

    private const STATES = [
        'catalogued',
        'planned',
        'in_progress',
        'blocked_external',
        'verified',
    ];

    public function test_product_catalog_records_are_complete_and_schema_valid(): void
    {
        $schema = $this->readJson('product/schema/feature.schema.json');
        $catalog = $this->readJson('product/catalog.json');
        $progress = $this->readJson('product/progress.json');

        $this->assertSame('1.0.0', $catalog['schemaVersion']);
        $this->assertSame('LinkPilot', $catalog['productName']);
        $this->assertSame(self::FEATURE_IDS, array_column($catalog['features'], 'id'));
        $this->assertCount(count(self::FEATURE_IDS), array_unique(array_column($catalog['features'], 'file')));

        $requiredFields = $schema['required'];
        sort($requiredFields);
        $allowedFields = array_keys($schema['properties']);
        sort($allowedFields);
        $this->assertSame($requiredFields, $allowedFields, 'Every allowed top-level field must be required.');
        $this->assertSame(self::STATES, $schema['properties']['status']['enum']);

        $requirementOwners = [];
        $featureStates = [];


        foreach ($catalog['features'] as $entry) {
            $this->assertSame("product/features/{$entry['id']}.json", $entry['file']);
            $feature = $this->readJson($entry['file']);
            $this->assertMatchesSchema($feature, $schema, $entry['id']);
            $featureStates[$entry['id']] = $feature['status'];


            $actualFields = array_keys($feature);
            sort($actualFields);
            $this->assertSame($requiredFields, $actualFields, "{$entry['id']} must have exactly the schema's top-level fields.");
            $this->assertSame('1.0.0', $feature['schemaVersion']);
            $this->assertSame($entry['id'], $feature['id']);
            $this->assertContains($feature['status'], self::STATES);
            $this->assertIsString($feature['area']);
            $this->assertNotSame('', trim($feature['area']));
            $this->assertIsString($feature['name']);
            $this->assertNotSame('', trim($feature['name']));

            foreach ($feature['requirements'] as $requirement) {
                $this->assertSame(['id', 'text', 'priority', 'status'], array_keys($requirement));
                $this->assertMatchesRegularExpression('/^[A-Z][A-Z0-9]*-[0-9]+$/', $requirement['id']);
                $this->assertNotSame('', trim($requirement['text']));
                $this->assertContains($requirement['priority'], ['P0', 'P1', 'P2', 'P3']);
                $this->assertContains($requirement['status'], self::STATES);
                $this->assertArrayNotHasKey($requirement['id'], $requirementOwners, "Duplicate requirement ID {$requirement['id']}.");
                $requirementOwners[$requirement['id']] = $entry['id'];
            }

            foreach ($feature['routes'] as $route) {
                $this->assertIsString($route);
                $this->assertStringStartsWith('/', $route);
            }

            foreach ($feature['dependencies'] as $dependency) {
                $this->assertContains($dependency, self::FEATURE_IDS, "Unknown feature dependency {$dependency}.");
                $this->assertNotSame($feature['id'], $dependency);
            }

            foreach ($feature['externalPrerequisites'] as $prerequisite) {
                $this->assertIsString($prerequisite);
                $this->assertNotSame('', trim($prerequisite));
            }

            foreach ($feature['acceptanceCriteria'] as $criterion) {
                $this->assertSame(['id', 'text'], array_keys($criterion));
                $this->assertNotSame('', trim($criterion['id']));
                $this->assertNotSame('', trim($criterion['text']));
            }

            foreach ($feature['referenceEvidence'] as $evidence) {
                $this->assertSame(
                    ['route', 'role', 'viewport', 'capturedAt', 'screenshotPath', 'observedBehaviors'],
                    array_keys($evidence),
                );
                $this->assertContains($evidence['role'], ['guest', 'user', 'admin']);
                $this->assertMatchesRegularExpression('/^[0-9]+x[0-9]+$/', $evidence['viewport']);
                $this->assertNotFalse(strtotime($evidence['capturedAt']));
                $this->assertFileExists(base_path($evidence['screenshotPath']));
                $this->assertNotEmpty($evidence['observedBehaviors']);
            }

            foreach ($feature['implementationEvidence'] as $evidence) {
                $this->assertSame(['type', 'description', 'reference', 'verifiedAt'], array_keys($evidence));
                $this->assertContains($evidence['type'], ['test', 'command', 'browser', 'migration', 'review']);
                $this->assertNotSame('', trim($evidence['description']));
                $this->assertNotSame('', trim($evidence['reference']));
                $this->assertNotFalse(strtotime($evidence['verifiedAt']));
            }

            if ($feature['status'] === 'verified') {
                $this->assertNotEmpty($feature['implementationEvidence'], "Verified feature {$feature['id']} needs implementation evidence.");
            }
        }

        $this->assertSame('1.0.0', $progress['schemaVersion']);
        $this->assertSame(self::FEATURE_IDS, array_column($progress['features'], 'id'));

        foreach ($progress['features'] as $record) {
            $this->assertSame(['id', 'state', 'lastVerifiedAt', 'checks', 'blockers'], array_keys($record));
            $this->assertSame($featureStates[$record['id']], $record['state'], "Feature and progress state must agree for {$record['id']}.");
            $this->assertContains($record['state'], self::STATES);
            $this->assertIsArray($record['checks']);
            $this->assertIsArray($record['blockers']);

            if ($record['state'] === 'verified') {
                $this->assertNotNull($record['lastVerifiedAt']);
                $this->assertNotEmpty($record['checks'], "Verified progress record {$record['id']} needs checks.");
            }

            if ($record['state'] === 'blocked_external') {
                $this->assertNotEmpty($record['blockers'], "Externally blocked record {$record['id']} must name its blocker.");
                $this->assertNotEmpty($record['checks'], "Externally blocked record {$record['id']} must retain completed local proof.");
            }
        }
    }

    public function test_every_prd_requirement_resolves_to_exactly_one_feature_record(): void
    {
        $catalog = $this->readJson('product/catalog.json');
        $owners = [];

        foreach ($catalog['features'] as $entry) {
            foreach ($this->readJson($entry['file'])['requirements'] as $requirement) {
                $owners[$requirement['id']][] = $entry['id'];
            }
        }

        $prd = file_get_contents(base_path('PRD.md'));
        $this->assertNotFalse($prd);
        preg_match_all('/^\| ([A-Z][A-Z0-9]*-[0-9]+) \|/m', $prd, $matches);
        $prdRequirementIds = array_values(array_unique($matches[1]));

        $this->assertNotEmpty($prdRequirementIds);

        foreach ($prdRequirementIds as $requirementId) {
            $this->assertArrayHasKey($requirementId, $owners, "PRD requirement {$requirementId} is missing from product records.");
            $this->assertCount(1, $owners[$requirementId], "PRD requirement {$requirementId} must have exactly one owner.");
        }
    }

    public function test_reference_manifest_inventories_every_capture_and_excludes_invalid_evidence(): void
    {
        $manifest = $this->readJson('product/reference/belink/manifest.json');
        $this->assertSame('1.0.0', $manifest['schemaVersion']);
        $this->assertNotEmpty($manifest['screenshots']);

        $manifestPaths = [];
        $excludedPaths = [];

        foreach ($manifest['screenshots'] as $screenshot) {
            $this->assertSame(
                [
                    'path',
                    'role',
                    'route',
                    'state',
                    'viewport',
                    'capturedAt',
                    'redactionNote',
                    'observedBehaviors',
                    'evidenceStatus',
                    'exclusionReason',
                ],
                array_keys($screenshot),
            );
            $this->assertArrayNotHasKey($screenshot['path'], $manifestPaths);
            $this->assertContains($screenshot['role'], ['guest', 'user', 'admin']);
            $this->assertContains($screenshot['evidenceStatus'], ['retained', 'excluded']);
            $this->assertMatchesRegularExpression('/^[0-9]+x[0-9]+$/', $screenshot['viewport']);
            $this->assertNotFalse(strtotime($screenshot['capturedAt']));
            $this->assertFileExists(base_path($screenshot['path']));
            $this->assertNotEmpty($screenshot['observedBehaviors']);

            $manifestPaths[$screenshot['path']] = true;

            if ($screenshot['evidenceStatus'] === 'excluded') {
                $this->assertNotSame('', trim((string) $screenshot['exclusionReason']));
                $excludedPaths[] = $screenshot['path'];
            } else {
                $this->assertNull($screenshot['exclusionReason']);
                $this->assertStringNotContainsStringIgnoringCase('require privacy review', $screenshot['redactionNote']);
                $this->assertStringNotContainsStringIgnoringCase('pending privacy review', $screenshot['redactionNote']);
            }
        }

        $capturedPaths = array_map(
            fn (string $path): string => str_replace(base_path().DIRECTORY_SEPARATOR, '', $path),
            glob(base_path('product/reference/belink/screenshots/*/*.png')) ?: [],
        );
        $manifestPaths = array_keys($manifestPaths);
        sort($capturedPaths);
        sort($manifestPaths);
        $this->assertSame($capturedPaths, $manifestPaths);

        $catalog = $this->readJson('product/catalog.json');

        foreach ($catalog['features'] as $entry) {
            foreach ($this->readJson($entry['file'])['referenceEvidence'] as $evidence) {
                $this->assertNotContains($evidence['screenshotPath'], $excludedPaths);
            }
        }
    }

    private function assertMatchesSchema(mixed $value, array $schema, string $path): void
    {
        if (array_key_exists('const', $schema)) {
            $this->assertSame($schema['const'], $value, "{$path} must match its schema constant.");
        }

        if (array_key_exists('enum', $schema)) {
            $this->assertContains($value, $schema['enum'], "{$path} must match a schema enum value.");
        }

        if (isset($schema['type'])) {
            $matchesType = match ($schema['type']) {
                'array' => is_array($value) && array_is_list($value),
                'object' => is_array($value) && ! array_is_list($value),
                'string' => is_string($value),
                'integer' => is_int($value),
                'number' => is_int($value) || is_float($value),
                'boolean' => is_bool($value),
                'null' => $value === null,
                default => false,
            };
            $this->assertTrue($matchesType, "{$path} must have schema type {$schema['type']}.");
        }

        if (is_string($value)) {
            if (isset($schema['minLength'])) {
                $this->assertGreaterThanOrEqual($schema['minLength'], mb_strlen($value), "{$path} is shorter than its schema minimum.");
            }

            if (isset($schema['pattern'])) {
                $this->assertMatchesRegularExpression('~'.$schema['pattern'].'~u', $value, "{$path} does not match its schema pattern.");
            }

            if (($schema['format'] ?? null) === 'date-time') {
                $this->assertNotFalse(strtotime($value), "{$path} must be a valid date-time.");
            }
        }

        if (! is_array($value)) {
            return;
        }

        if (($schema['type'] ?? null) === 'array') {
            if (isset($schema['minItems'])) {
                $this->assertGreaterThanOrEqual($schema['minItems'], count($value), "{$path} has too few items.");
            }

            if (($schema['uniqueItems'] ?? false) === true) {
                $encoded = array_map(static fn (mixed $item): string => json_encode($item, JSON_THROW_ON_ERROR), $value);
                $this->assertSameSize(array_unique($encoded), $encoded, "{$path} must contain unique items.");
            }

            if (isset($schema['items'])) {
                foreach ($value as $index => $item) {
                    $this->assertMatchesSchema($item, $schema['items'], "{$path}[{$index}]");
                }
            }

            return;
        }

        foreach ($schema['required'] ?? [] as $required) {
            $this->assertArrayHasKey($required, $value, "{$path} is missing required property {$required}.");
        }

        if (($schema['additionalProperties'] ?? true) === false) {
            $unknown = array_diff(array_keys($value), array_keys($schema['properties'] ?? []));
            $this->assertSame([], array_values($unknown), "{$path} contains properties not allowed by the schema.");
        }

        foreach ($schema['properties'] ?? [] as $property => $propertySchema) {
            if (array_key_exists($property, $value)) {
                $this->assertMatchesSchema($value[$property], $propertySchema, "{$path}.{$property}");
            }
        }
    }

    private function readJson(string $path): array
    {
        $absolutePath = base_path($path);
        $this->assertFileExists($absolutePath);

        return json_decode(
            file_get_contents($absolutePath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
