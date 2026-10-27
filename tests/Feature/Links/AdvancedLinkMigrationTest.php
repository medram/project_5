<?php

namespace Tests\Feature\Links;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class AdvancedLinkMigrationTest extends TestCase
{
    public function test_legacy_link_data_is_backfilled_and_campaign_rollbacks_refuse_data_loss(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'linkpilot-link-migration-');
        $this->assertIsString($databasePath);

        $connection = 'advanced_link_migration_probe';
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
            $this->runMigrationsThrough($connection, '2026_10_20_002000_create_link_tags_tables.php');
            $legacy = $this->seedLegacyLink($connection);

            $advancedDelivery = require database_path('migrations/2026_10_22_190000_add_advanced_delivery_fields_to_links.php');
            $advancedDelivery->up();
            $routingAndRevisions = require database_path('migrations/2026_10_22_190100_create_link_routing_and_revisions_tables.php');
            $routingAndRevisions->up();

            $link = DB::connection($connection)->table('links')->where('id', $legacy['linkId'])->first();
            $this->assertSame(2, (int) $link->click_count);
            $this->assertSame('approved', $link->approval_status);
            $this->assertSame('overlay', $link->delivery_mode);
            $this->assertSame($legacy['folderId'], (int) $link->folder_id);
            $this->assertSame($legacy['overlayId'], (int) $link->link_overlay_id);
            $this->assertSame('https://example.test/legacy', $link->destination_url);
            $this->assertSame(2, DB::connection($connection)->table('clicks')->where('link_id', $legacy['linkId'])->count());
            $this->assertSame('Legacy folder', DB::connection($connection)->table('folders')->where('id', $legacy['folderId'])->value('name'));
            $this->assertSame('Legacy heading', DB::connection($connection)->table('link_overlays')->where('id', $legacy['overlayId'])->value('heading'));

            DB::connection($connection)->table('link_revisions')->insert([
                'workspace_id' => $legacy['workspaceId'],
                'link_id' => $legacy['linkId'],
                'revision_number' => 1,
                'status' => 'approved',
                'snapshot' => json_encode(['destination_url' => 'https://example.test/legacy'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                $routingAndRevisions->down();
                $this->fail('Expected revision history to prevent rollback.');
            } catch (LogicException $exception) {
                $this->assertSame('Cannot remove link routing rules or revision history while campaign data exists.', $exception->getMessage());
            }

            DB::connection($connection)->table('links')->where('id', $legacy['linkId'])->update(['max_clicks' => 1]);
            try {
                $advancedDelivery->down();
                $this->fail('Expected campaign controls to prevent rollback.');
            } catch (LogicException $exception) {
                $this->assertSame('Cannot remove advanced link fields while they contain active campaign data.', $exception->getMessage());
            }

            $this->assertTrue(Schema::connection($connection)->hasColumn('links', 'click_count'));
            $this->assertTrue(Schema::connection($connection)->hasTable('link_revisions'));
            $this->assertSame('ok', DB::connection($connection)->selectOne('PRAGMA integrity_check')->integrity_check);
            $this->assertSame([], DB::connection($connection)->select('PRAGMA foreign_key_check'));
        } finally {
            DB::setDefaultConnection($previousConnection);
            DB::purge($connection);
            @unlink($databasePath);
        }
    }

    /** @return array<int, string> */
    private function migrationsThrough(string $lastName): array
    {
        $paths = collect(glob(database_path('migrations/*.php')))->sort()->values();
        $lastIndex = $paths->search(fn (string $path): bool => basename($path) === $lastName);

        if ($lastIndex === false) {
            $this->fail("Unknown migration [{$lastName}].");
        }

        return $paths->take($lastIndex + 1)->all();
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

    /** @return array{userId: int, workspaceId: int, linkId: int, folderId: int, overlayId: int} */
    private function seedLegacyLink(string $connection): array
    {
        $database = DB::connection($connection);
        $now = now();
        $userId = (int) $database->table('users')->insertGetId([
            'name' => 'Legacy owner',
            'email' => 'legacy-link-owner@example.test',
            'password' => 'irrelevant-password-hash',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $workspaceId = (int) $database->table('workspaces')->insertGetId([
            'name' => 'Legacy workspace',
            'slug' => 'legacy-workspace',
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
        $folderId = (int) $database->table('folders')->insertGetId([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'name' => 'Legacy folder',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $overlayId = (int) $database->table('link_overlays')->insertGetId([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'name' => 'Legacy overlay',
            'heading' => 'Legacy heading',
            'cta_label' => 'Continue',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $linkId = (int) $database->table('links')->insertGetId([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'folder_id' => $folderId,
            'link_overlay_id' => $overlayId,
            'slug' => 'legacy-link',
            'destination_url' => 'https://example.test/legacy',
            'title' => 'Legacy link',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $database->table('clicks')->insert([
            ['link_id' => $linkId, 'occurred_at' => $now],
            ['link_id' => $linkId, 'occurred_at' => $now],
        ]);

        return [
            'userId' => $userId,
            'workspaceId' => $workspaceId,
            'linkId' => $linkId,
            'folderId' => $folderId,
            'overlayId' => $overlayId,
        ];
    }
}
