<?php

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase8MigrationTest extends TestCase
{
    private const PRE_PHASE_EIGHT = '2026_10_26_124200_add_source_delivery_to_webhook_deliveries.php';

    public function test_expansion_backfills_catalog_overrides_and_media_purposes_without_data_loss(): void
    {
        $probe = $this->probeConnection();
        $this->runMigrations($probe, $this->migrationsThrough(self::PRE_PHASE_EIGHT));
        $fixture = $this->seedPreMigrationData($probe);

        $before = $this->tableCounts($probe);
        $expires = DB::connection($probe)->table('content_media')->orderBy('id')->pluck('expires_at', 'id')->all();

        $this->runMigrations($probe, [database_path('migrations/2026_11_12_000000_expand_workspace_billing_catalog.php')]);

        $pro = DB::connection($probe)->table('plans')->where('code', 'pro')->sole();
        $this->assertSame(29000, (int) $pro->annual_price);
        $this->assertSame(100, (int) $pro->folder_limit);
        $this->assertSame(250, (int) $pro->qr_campaign_limit);
        $this->assertSame(10, (int) $pro->bio_page_limit);
        $this->assertSame(5, (int) $pro->api_key_limit);
        $this->assertSame(10 * 1024 * 1024 * 1024, (int) $pro->storage_byte_limit);
        $free = DB::connection($probe)->table('plans')->where('code', 'free')->sole();
        $this->assertSame(100 * 1024 * 1024, (int) $free->storage_byte_limit);

        $this->assertDatabaseHas('workspace_plan_overrides', [
            'workspace_id' => $fixture['proWorkspace'],
            'plan_id' => $fixture['proPlanId'],
            'source' => 'legacy_manual',
        ], $probe);
        $this->assertDatabaseMissing('workspace_plan_overrides', [
            'workspace_id' => $fixture['freeWorkspace'],
        ], $probe);

        $this->assertSame('file', DB::connection($probe)->table('content_media')->where('id', $fixture['pdfMedia'])->value('purpose'));
        $this->assertSame('image', DB::connection($probe)->table('content_media')->where('id', $fixture['imageMedia'])->value('purpose'));
        $this->assertSame(
            $expires,
            DB::connection($probe)->table('content_media')->orderBy('id')->pluck('expires_at', 'id')->all(),
        );

        $this->assertSame($before, $this->tableCounts($probe));
        $this->assertSame('ok', DB::connection($probe)->selectOne('PRAGMA integrity_check')->integrity_check);
        $this->assertSame([], DB::connection($probe)->select('PRAGMA foreign_key_check'));
    }

    public function test_rollback_reconstructs_legacy_entitlements_and_preserves_records(): void
    {
        $probe = $this->probeConnection();
        $this->runMigrations($probe, $this->migrationsThrough(self::PRE_PHASE_EIGHT));
        $fixture = $this->seedPreMigrationData($probe);
        $this->runMigrations($probe, [database_path('migrations/2026_11_12_000000_expand_workspace_billing_catalog.php')]);
        $before = $this->tableCounts($probe);

        Artisan::call('migrate:rollback', [
            '--database' => $probe,
            '--step' => 1,
            '--force' => true,
        ]);

        $this->assertFalse(Schema::connection($probe)->hasColumn('plans', 'annual_price'));
        $this->assertFalse(Schema::connection($probe)->hasColumn('content_media', 'purpose'));
        $this->assertFalse(Schema::connection($probe)->hasTable('workspace_plan_overrides'));
        $this->assertFalse(Schema::connection($probe)->hasTable('subscriptions'));

        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $fixture['proWorkspace'],
            'plan_id' => $fixture['proPlanId'],
        ], $probe);
        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $fixture['freeWorkspace'],
            'plan_id' => $fixture['freePlanId'],
        ], $probe);

        $this->assertSame($before, $this->tableCounts($probe));
        $this->assertSame('ok', DB::connection($probe)->selectOne('PRAGMA integrity_check')->integrity_check);
        $this->assertSame([], DB::connection($probe)->select('PRAGMA foreign_key_check'));
    }

    private function probeConnection(): string
    {
        $name = 'phase8_billing_probe';

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
     * @return array{proWorkspace: int, freeWorkspace: int, proPlanId: int, freePlanId: int, pdfMedia: int, imageMedia: int}
     */
    private function seedPreMigrationData(string $connection): array
    {
        $now = now();
        $db = DB::connection($connection);
        $userId = (int) $db->table('users')->insertGetId([
            'name' => 'Phase 8 Probe',
            'email' => 'phase8-probe@example.test',
            'password' => 'secret',
            'email_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $proPlanId = (int) $db->table('plans')->where('code', 'pro')->value('id');
        $freePlanId = (int) $db->table('plans')->where('code', 'free')->value('id');

        $proWorkspace = (int) $db->table('workspaces')->insertGetId([
            'name' => 'Pro Workspace',
            'slug' => 'pro-workspace',
            'owner_id' => $userId,
            'personal_owner_user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $freeWorkspace = (int) $db->table('workspaces')->insertGetId([
            'name' => 'Free Workspace',
            'slug' => 'free-workspace',
            'owner_id' => $userId,
            'personal_owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([$proWorkspace => 'owner'] as $workspaceId => $role) {
            $db->table('workspace_user')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $userId,
                'role' => $role,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $db->table('workspace_subscriptions')->insert([
            [
                'workspace_id' => $proWorkspace,
                'plan_id' => $proPlanId,
                'status' => 'active',
                'source' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'workspace_id' => $freeWorkspace,
                'plan_id' => $freePlanId,
                'status' => 'active',
                'source' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $db->table('links')->insert([
            'user_id' => $userId,
            'workspace_id' => $proWorkspace,
            'slug' => 'phase8-probe-link',
            'destination_url' => 'https://example.test/probe',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $db->table('folders')->insert([
            'user_id' => $userId,
            'workspace_id' => $proWorkspace,
            'name' => 'Probe folder',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $db->table('bio_pages')->insert([
            'user_id' => $userId,
            'workspace_id' => $proWorkspace,
            'handle' => 'phase8-probe',
            'display_name' => 'Probe bio',
            'is_published' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pdfMedia = (int) $db->table('content_media')->insertGetId([
            'workspace_id' => $proWorkspace,
            'uploaded_by' => $userId,
            'public_id' => '00000000-0000-4000-8000-000000000001',
            'disk' => 'local',
            'path' => 'workspaces/phase8-probe.pdf',
            'original_name' => 'probe.pdf',
            'mime_type' => 'application/pdf',
            'byte_size' => 2048,
            'sha256' => str_repeat('a', 64),
            'visibility' => 'private',
            'scan_state' => 'clean',
            'expires_at' => $now->copy()->addYear(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $imageMedia = (int) $db->table('content_media')->insertGetId([
            'workspace_id' => $proWorkspace,
            'uploaded_by' => $userId,
            'public_id' => '00000000-0000-4000-8000-000000000002',
            'disk' => 'local',
            'path' => 'workspaces/phase8-probe.png',
            'original_name' => 'probe.png',
            'mime_type' => 'image/png',
            'byte_size' => 1024,
            'sha256' => str_repeat('b', 64),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'expires_at' => $now->copy()->addYear(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'proWorkspace' => $proWorkspace,
            'freeWorkspace' => $freeWorkspace,
            'proPlanId' => $proPlanId,
            'freePlanId' => $freePlanId,
            'pdfMedia' => $pdfMedia,
            'imageMedia' => $imageMedia,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(string $connection): array
    {
        $db = DB::connection($connection);

        return [
            'users' => $db->table('users')->count(),
            'workspaces' => $db->table('workspaces')->count(),
            'links' => $db->table('links')->count(),
            'folders' => $db->table('folders')->count(),
            'bio_pages' => $db->table('bio_pages')->count(),
            'content_media' => $db->table('content_media')->count(),
            'workspace_subscriptions' => $db->table('workspace_subscriptions')->count(),
        ];
    }
}
