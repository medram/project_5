<?php

namespace Tests\Feature\Content;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class Phase6MigrationTest extends TestCase
{
    public function test_content_media_migration_preserves_legacy_bio_link_and_analytics_references(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'linkpilot-phase6-migration-');
        $this->assertIsString($databasePath);

        $connection = 'phase6_migration_probe';
        $previousConnection = DB::getDefaultConnection();
        config()->set("database.connections.{$connection}", [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]);
        DB::purge($connection);

        try {
            DB::setDefaultConnection($connection);
            $this->runMigrationsThrough($connection, '2026_10_22_220000_create_qr_campaign_tables.php');
            $legacy = $this->seedLegacyContent($connection);
            $before = $this->legacySnapshot($connection, $legacy);

            $migration = require database_path('migrations/2026_10_23_000000_create_content_media_table.php');
            $migration->up();

            $this->assertTrue(Schema::connection($connection)->hasTable('content_media'));
            $this->assertSame($before, $this->legacySnapshot($connection, $legacy));
            $this->assertSame('ok', DB::connection($connection)->selectOne('PRAGMA integrity_check')->integrity_check);
            $this->assertSame([], DB::connection($connection)->select('PRAGMA foreign_key_check'));
        } finally {
            DB::setDefaultConnection($previousConnection);
            DB::purge($connection);
            @unlink($databasePath);
        }
    }

    public function test_analytics_dimension_migration_preserves_existing_event_identity_and_references(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'linkpilot-analytics-migration-');
        $this->assertIsString($databasePath);

        $connection = 'analytics_migration_probe';
        $previousConnection = DB::getDefaultConnection();
        config()->set("database.connections.{$connection}", [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]);
        DB::purge($connection);

        try {
            DB::setDefaultConnection($connection);
            $this->runMigrationsThrough($connection, '2026_10_23_000600_create_tracking_pixel_rules.php');
            $legacy = $this->seedLegacyContent($connection);
            $before = $this->legacySnapshot($connection, $legacy);

            $migration = require database_path('migrations/2026_10_26_000100_add_country_and_utm_to_analytics_events.php');
            $migration->up();

            $this->assertSame($before, $this->legacySnapshot($connection, $legacy));
            $this->assertSame([
                'country_code' => null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'utm_term' => null,
                'utm_content' => null,
            ], (array) DB::connection($connection)->table('analytics_events')->where('id', $legacy['analyticsEventId'])->first([
                'country_code',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
            ]));
            $this->assertFalse(Schema::connection($connection)->hasColumn('analytics_events', 'ip_address'));
            $this->assertSame('ok', DB::connection($connection)->selectOne('PRAGMA integrity_check')->integrity_check);
            $this->assertSame([], DB::connection($connection)->select('PRAGMA foreign_key_check'));
        } finally {
            DB::setDefaultConnection($previousConnection);
            DB::purge($connection);
            @unlink($databasePath);
        }
    }

    public function test_link_page_variant_migration_backfills_content_without_mutating_analytics(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'linkpilot-variant-migration-');
        $this->assertIsString($databasePath);

        $connection = 'phase6_variant_migration_probe';
        $previousConnection = DB::getDefaultConnection();
        config()->set("database.connections.{$connection}", [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]);
        DB::purge($connection);

        try {
            DB::setDefaultConnection($connection);
            $this->runMigrationsThrough($connection, '2026_10_23_000300_create_link_page_leads.php');
            $legacy = $this->seedLegacyContent($connection);
            $database = DB::connection($connection);
            $now = now('UTC');
            $linkPageBlockId = (int) $database->table('link_page_blocks')->insertGetId([
                'link_page_id' => $legacy['linkPageId'],
                'type' => 'text',
                'content' => json_encode(['text' => 'Legacy block'], JSON_THROW_ON_ERROR),
                'content_media_id' => null,
                'position' => 7,
                'is_active' => true,
                'starts_at' => null,
                'ends_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $before = [
                'item' => (array) $database->table('link_page_items')
                    ->where('id', $legacy['linkPageItemId'])
                    ->first(['id', 'link_page_id', 'title', 'destination_url', 'position', 'is_active']),
                'block' => (array) $database->table('link_page_blocks')
                    ->where('id', $linkPageBlockId)
                    ->first(['id', 'link_page_id', 'type', 'content', 'position', 'is_active']),
                'event' => (array) $database->table('analytics_events')
                    ->where('id', $legacy['analyticsEventId'])
                    ->first(['id', 'uuid', 'link_page_id', 'link_page_item_id', 'event_name', 'occurred_at']),
            ];
            $migrationPath = database_path('migrations/2026_10_23_000400_create_link_page_variants.php');
            $this->assertFileExists($migrationPath);

            $migration = require $migrationPath;
            $migration->up();

            $control = $database->table('link_page_variants')
                ->where('link_page_id', $legacy['linkPageId'])
                ->where('is_control', true)
                ->sole();
            $this->assertSame(100, (int) $control->weight);
            $this->assertSame($control->id, $database->table('link_page_items')
                ->where('id', $legacy['linkPageItemId'])
                ->value('link_page_variant_id'));
            $this->assertSame($control->id, $database->table('link_page_blocks')
                ->where('id', $linkPageBlockId)
                ->value('link_page_variant_id'));

            $after = [
                'item' => (array) $database->table('link_page_items')
                    ->where('id', $legacy['linkPageItemId'])
                    ->first(['id', 'link_page_id', 'title', 'destination_url', 'position', 'is_active']),
                'block' => (array) $database->table('link_page_blocks')
                    ->where('id', $linkPageBlockId)
                    ->first(['id', 'link_page_id', 'type', 'content', 'position', 'is_active']),
                'event' => (array) $database->table('analytics_events')
                    ->where('id', $legacy['analyticsEventId'])
                    ->first(['id', 'uuid', 'link_page_id', 'link_page_item_id', 'event_name', 'occurred_at']),
            ];

            $this->assertSame($before, $after);
            $this->assertNull($database->table('analytics_events')
                ->where('id', $legacy['analyticsEventId'])
                ->value('link_page_variant_id'));
            $this->assertSame('ok', $database->selectOne('PRAGMA integrity_check')->integrity_check);
            $this->assertSame([], $database->select('PRAGMA foreign_key_check'));
        } finally {
            DB::setDefaultConnection($previousConnection);
            DB::purge($connection);
            @unlink($databasePath);
        }
    }

    /** @return array<int, string> */
    private function migrationsThrough(string $lastName): array
    {
        $paths = glob(database_path('migrations/*.php'));
        if (! is_array($paths)) {
            throw new RuntimeException('Unable to enumerate database migrations.');
        }

        sort($paths, SORT_STRING);
        $names = array_map(static fn (string $path): string => basename($path), $paths);
        $lastIndex = array_search($lastName, $names, true);

        if (! is_int($lastIndex)) {
            throw new RuntimeException("Unknown migration [{$lastName}].");
        }

        return array_slice($paths, 0, $lastIndex + 1);
    }

    private function runMigrationsThrough(string $connection, string $lastName): void
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => $connection,
            '--path' => $this->migrationsThrough($lastName),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
    }

    /** @return array{userId:int,workspaceId:int,bioPageId:int,bioLinkId:int,linkPageId:int,linkPageItemId:int,analyticsEventId:int} */
    private function seedLegacyContent(string $connection): array
    {
        $database = DB::connection($connection);
        $now = now('UTC');
        $userId = (int) $database->table('users')->insertGetId([
            'name' => 'Phase 6 Legacy Owner',
            'email' => 'phase6-legacy-owner@example.test',
            'password' => 'irrelevant-password-hash',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $workspaceId = (int) $database->table('workspaces')->insertGetId([
            'name' => 'Phase 6 Legacy Workspace',
            'slug' => 'phase6-legacy-workspace',
            'personal_owner_user_id' => $userId,
            'owner_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $database->table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => 'owner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $database->table('users')->where('id', $userId)->update(['current_workspace_id' => $workspaceId]);

        $bioPageId = (int) $database->table('bio_pages')->insertGetId([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'handle' => 'phase6-legacy-bio',
            'display_name' => 'Legacy Bio',
            'bio' => 'Existing bio content',
            'is_published' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $bioLinkId = (int) $database->table('bio_links')->insertGetId([
            'bio_page_id' => $bioPageId,
            'title' => 'Legacy bio link',
            'destination_url' => 'https://example.test/bio-link',
            'position' => 3,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $linkPageId = (int) $database->table('link_pages')->insertGetId([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'name' => 'Legacy Link Page',
            'slug' => 'phase6-legacy-page',
            'title' => 'Legacy page title',
            'description' => 'Existing link-page description',
            'is_published' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $linkPageItemId = (int) $database->table('link_page_items')->insertGetId([
            'link_page_id' => $linkPageId,
            'title' => 'Legacy page item',
            'destination_url' => 'https://example.test/page-item',
            'position' => 4,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $analyticsEventId = (int) $database->table('analytics_events')->insertGetId([
            'uuid' => '00000000-0000-4000-8000-000000000601',
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'bio_page_id' => $bioPageId,
            'bio_link_id' => $bioLinkId,
            'link_page_id' => $linkPageId,
            'link_page_item_id' => $linkPageItemId,
            'event_name' => 'link_page.view',
            'occurred_at' => $now,
            'visitor_hash' => str_repeat('a', 64),
            'visitor_key_version' => 'v1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return compact('userId', 'workspaceId', 'bioPageId', 'bioLinkId', 'linkPageId', 'linkPageItemId', 'analyticsEventId');
    }

    /** @param array{userId:int,workspaceId:int,bioPageId:int,bioLinkId:int,linkPageId:int,linkPageItemId:int,analyticsEventId:int} $ids
     * @return array<string, array<string, mixed>|null>
     */
    private function legacySnapshot(string $connection, array $ids): array
    {
        $database = DB::connection($connection);

        return [
            'bio_page' => (array) $database->table('bio_pages')->where('id', $ids['bioPageId'])->first(['id', 'workspace_id', 'handle', 'display_name', 'bio', 'is_published']),
            'bio_link' => (array) $database->table('bio_links')->where('id', $ids['bioLinkId'])->first(['id', 'bio_page_id', 'title', 'destination_url', 'position', 'is_active']),
            'link_page' => (array) $database->table('link_pages')->where('id', $ids['linkPageId'])->first(['id', 'workspace_id', 'name', 'slug', 'title', 'description', 'is_published']),
            'link_page_item' => (array) $database->table('link_page_items')->where('id', $ids['linkPageItemId'])->first(['id', 'link_page_id', 'title', 'destination_url', 'position', 'is_active']),
            'analytics_event' => (array) $database->table('analytics_events')->where('id', $ids['analyticsEventId'])->first(['id', 'uuid', 'bio_page_id', 'bio_link_id', 'link_page_id', 'link_page_item_id', 'event_name', 'occurred_at']),
        ];
    }
}
