<?php

namespace Tests\Feature\Workspaces;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

/**
 * Proves the workspace ownership migrations against the real migration
 * chronology on a probe database instead of the already-migrated test schema.
 */
class WorkspaceMigrationChronologyTest extends TestCase
{
    private const OWNED_TABLES = [
        'links',
        'bio_pages',
        'folders',
        'custom_domains',
        'tracking_pixels',
        'link_pages',
        'link_overlays',
        'webhooks',
        'analytics_events',
    ];

    public function test_legacy_upgrade_backfill_runs_before_owner_id_column_exists(): void
    {
        $probe = $this->probeConnection();
        $this->runMigrations($probe, $this->migrationsThrough('2026_10_19_001600_expand_workspace_ownership.php'));
        $fixture = $this->seedLegacyData($probe);

        $this->assertFalse(
            Schema::connection($probe)->hasColumn('workspaces', 'owner_id'),
            'The expand migration must not create workspaces.owner_id.',
        );

        $this->runMigrations($probe, [
            database_path('migrations/2026_10_19_001700_backfill_legacy_workspaces.php'),
        ]);

        $this->assertFalse(
            Schema::connection($probe)->hasColumn('workspaces', 'owner_id'),
            'The legacy backfill must run without workspaces.owner_id, which only exists after 2026_10_19_001900.',
        );

        $workspaceId = DB::connection($probe)->table('workspaces')
            ->where('personal_owner_user_id', $fixture['userId'])
            ->value('id');
        $this->assertNotNull($workspaceId, 'The backfill must create the personal workspace.');
        $workspaceId = (int) $workspaceId;

        $this->assertSame(
            "personal-{$fixture['userId']}",
            DB::connection($probe)->table('workspaces')->where('id', $workspaceId)->value('slug'),
        );
        $this->assertTrue(
            DB::connection($probe)->table('workspace_user')
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $fixture['userId'])
                ->where('role', 'owner')
                ->exists(),
            'The backfill must create the owner membership.',
        );
        $this->assertSame(
            $workspaceId,
            (int) DB::connection($probe)->table('users')->where('id', $fixture['userId'])->value('current_workspace_id'),
        );

        foreach ($fixture['rowIds'] as $table => $id) {
            $this->assertSame(
                $workspaceId,
                (int) DB::connection($probe)->table($table)->where('id', $id)->value('workspace_id'),
                $table,
            );
            $this->assertSame(
                $fixture['userId'],
                (int) DB::connection($probe)->table($table)->where('id', $id)->value('user_id'),
                $table,
            );
        }

        $this->assertSame(
            $fixture['proPlanId'],
            (int) DB::connection($probe)->table('workspace_subscriptions')
                ->where('workspace_id', $workspaceId)
                ->value('plan_id'),
        );
    }

    public function test_owner_enforcement_derives_owner_id_from_the_owner_membership(): void
    {
        $probe = $this->probeConnection();
        $this->runMigrations($probe, $this->migrationsThrough('2026_10_19_001600_expand_workspace_ownership.php'));
        $fixture = $this->seedLegacyData($probe);

        $this->runMigrations($probe, $this->migrationsThrough('2026_10_19_001900_enforce_workspace_account_deletion_ownership.php'));

        $this->assertFalse(Schema::connection($probe)->hasTable('user_subscriptions'));
        $this->assertTrue(Schema::connection($probe)->hasColumn('workspaces', 'owner_id'));

        $workspaceId = (int) DB::connection($probe)->table('workspaces')
            ->where('personal_owner_user_id', $fixture['userId'])
            ->value('id');

        $this->assertSame(
            $fixture['userId'],
            (int) DB::connection($probe)->table('workspaces')->where('id', $workspaceId)->value('owner_id'),
            'The enforcement migration must derive the canonical owner from the owner membership.',
        );

        foreach ($fixture['rowIds'] as $table => $id) {
            $this->assertSame(
                $workspaceId,
                (int) DB::connection($probe)->table($table)->where('id', $id)->value('workspace_id'),
                $table,
            );
            $this->assertSame(
                $fixture['userId'],
                (int) DB::connection($probe)->table($table)->where('id', $id)->value('user_id'),
                $table,
            );
        }
    }

    public function test_owner_preflight_failure_leaves_schema_unchanged_and_retry_succeeds(): void
    {
        $probe = $this->probeConnection();
        $this->runMigrations($probe, $this->migrationsThrough('2026_10_19_001600_expand_workspace_ownership.php'));
        $fixture = $this->seedLegacyData($probe);
        $this->runMigrations($probe, $this->migrationsThrough('2026_10_19_001800_contract_workspace_ownership.php'));

        $now = now();
        $secondUserId = (int) DB::connection($probe)->table('users')->insertGetId([
            'name' => 'Second Owner',
            'email' => 'second-owner@example.test',
            'password' => 'irrelevant-password-hash',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $ownerlessId = (int) DB::connection($probe)->table('workspaces')->insertGetId([
            'name' => 'Ownerless workspace',
            'slug' => 'ownerless-workspace',
            'personal_owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $twoOwnersId = (int) DB::connection($probe)->table('workspaces')->insertGetId([
            'name' => 'Two owners workspace',
            'slug' => 'two-owners-workspace',
            'personal_owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::connection($probe)->table('workspace_user')->insert([
            [
                'workspace_id' => $twoOwnersId,
                'user_id' => $fixture['userId'],
                'role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'workspace_id' => $twoOwnersId,
                'user_id' => $secondUserId,
                'role' => 'owner',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $enforcement = database_path('migrations/2026_10_19_001900_enforce_workspace_account_deletion_ownership.php');

        $exception = null;
        try {
            $this->runMigrations($probe, [$enforcement]);
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception, 'Expected the owner preflight to reject a workspace without an owner membership.');
        $this->assertStringContainsString(
            "Cannot establish canonical owner for workspace [{$ownerlessId}]",
            $exception->getMessage(),
        );
        $this->assertFalse(
            Schema::connection($probe)->hasColumn('workspaces', 'owner_id'),
            'The owner preflight must fail before the schema changes so the migration can be repaired and retried.',
        );

        DB::connection($probe)->table('workspace_user')->insert([
            'workspace_id' => $ownerlessId,
            'user_id' => $secondUserId,
            'role' => 'owner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $exception = null;
        try {
            $this->runMigrations($probe, [$enforcement]);
        } catch (Throwable $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception, 'Expected the owner preflight to reject a workspace with two owner memberships.');
        $this->assertStringContainsString(
            "Cannot establish canonical owner for workspace [{$twoOwnersId}]",
            $exception->getMessage(),
        );
        $this->assertFalse(
            Schema::connection($probe)->hasColumn('workspaces', 'owner_id'),
            'The owner preflight must fail before the schema changes so the migration can be repaired and retried.',
        );

        DB::connection($probe)->table('workspace_user')
            ->where('workspace_id', $twoOwnersId)
            ->where('user_id', $fixture['userId'])
            ->delete();

        $this->runMigrations($probe, [$enforcement]);

        $this->assertTrue(Schema::connection($probe)->hasColumn('workspaces', 'owner_id'));

        $personalWorkspaceId = (int) DB::connection($probe)->table('workspaces')
            ->where('personal_owner_user_id', $fixture['userId'])
            ->value('id');

        foreach ([
            $personalWorkspaceId => $fixture['userId'],
            $ownerlessId => $secondUserId,
            $twoOwnersId => $secondUserId,
        ] as $workspaceId => $expectedOwnerId) {
            $this->assertSame(
                $expectedOwnerId,
                (int) DB::connection($probe)->table('workspaces')->where('id', $workspaceId)->value('owner_id'),
                "workspace [{$workspaceId}]",
            );
        }
    }

    private function probeConnection(): string
    {
        $name = 'migration_chronology_probe';

        config()->set("database.connections.{$name}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ]);

        DB::purge($name);
        DB::connection($name);

        return $name;
    }

    /**
     * @return array<int, string>
     */
    private function migrationsThrough(string $lastName): array
    {
        $paths = collect(glob(database_path('migrations/*.php')))->sort()->values();

        $lastIndex = $paths->search(fn (string $path): bool => basename($path) === $lastName);

        if ($lastIndex === false) {
            $this->fail("Unknown migration [{$lastName}].");
        }

        return $paths->take($lastIndex + 1)->all();
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function runMigrations(string $connection, array $paths): void
    {
        Artisan::call('migrate', [
            '--database' => $connection,
            '--path' => $paths,
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    /**
     * Insert rows that predate workspace ownership exactly as an upgraded
     * installation would contain them at the 2026_10_19_001700 chronology point.
     *
     * @return array{userId: int, proPlanId: int, rowIds: array<string, int>}
     */
    private function seedLegacyData(string $connection): array
    {
        $now = now();
        $userId = (int) DB::connection($connection)->table('users')->insertGetId([
            'name' => 'Legacy Owner',
            'email' => 'legacy-owner@example.test',
            'password' => 'irrelevant-password-hash',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $proPlanId = (int) DB::connection($connection)->table('plans')->where('code', 'pro')->value('id');

        DB::connection($connection)->table('user_subscriptions')->insert([
            'user_id' => $userId,
            'plan_id' => $proPlanId,
            'status' => 'active',
            'source' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $linkId = (int) DB::connection($connection)->table('links')->insertGetId([
            'user_id' => $userId,
            'slug' => 'legacy-link',
            'destination_url' => 'https://example.com/legacy',
            'title' => 'Legacy link',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rowIds = [
            'links' => $linkId,
            'bio_pages' => (int) DB::connection($connection)->table('bio_pages')->insertGetId([
                'user_id' => $userId,
                'handle' => 'legacy-bio',
                'display_name' => 'Legacy bio',
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'folders' => (int) DB::connection($connection)->table('folders')->insertGetId([
                'user_id' => $userId,
                'name' => 'Legacy folder',
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'custom_domains' => (int) DB::connection($connection)->table('custom_domains')->insertGetId([
                'user_id' => $userId,
                'hostname' => 'legacy.example.test',
                'verification_token' => str_repeat('a', 40),
                'status' => 'pending_dns',
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'tracking_pixels' => (int) DB::connection($connection)->table('tracking_pixels')->insertGetId([
                'user_id' => $userId,
                'name' => 'Legacy pixel',
                'provider' => 'meta_pixel',
                'provider_id' => '123456789012345',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'link_pages' => (int) DB::connection($connection)->table('link_pages')->insertGetId([
                'user_id' => $userId,
                'name' => 'Legacy page',
                'slug' => 'legacy-page',
                'title' => 'Legacy page',
                'is_published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'link_overlays' => (int) DB::connection($connection)->table('link_overlays')->insertGetId([
                'user_id' => $userId,
                'name' => 'Legacy overlay',
                'heading' => 'Legacy heading',
                'cta_label' => 'Continue',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'webhooks' => (int) DB::connection($connection)->table('webhooks')->insertGetId([
                'user_id' => $userId,
                'name' => 'Legacy webhook',
                'url' => 'https://hooks.example.test/events',
                'secret' => 'encrypted-secret',
                'events' => json_encode(['link.clicked'], JSON_THROW_ON_ERROR),
                'revision' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'analytics_events' => (int) DB::connection($connection)->table('analytics_events')->insertGetId([
                'uuid' => '00000000-0000-4000-8000-000000000001',
                'user_id' => $userId,
                'link_id' => $linkId,
                'event_name' => 'link.clicked',
                'occurred_at' => $now,
                'visitor_hash' => str_repeat('b', 64),
                'visitor_key_version' => 'v1',
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];

        foreach (self::OWNED_TABLES as $table) {
            $this->assertArrayHasKey($table, $rowIds);
        }

        return [
            'userId' => $userId,
            'proPlanId' => $proPlanId,
            'rowIds' => $rowIds,
        ];
    }
}
