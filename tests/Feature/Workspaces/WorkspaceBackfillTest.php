<?php

namespace Tests\Feature\Workspaces;

use App\Models\Link;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkspaceBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_assigns_each_legacy_resource_to_a_deterministic_personal_workspace_and_preserves_paid_plan(): void
    {
        $this->createLegacySubscriptionTable();
        $this->makeWorkspaceIdsNullable();
        $this->makeWorkspaceOwnerIdNullable();
        $owner = User::withoutEvents(fn () => User::factory()->create());
        $freeUser = User::withoutEvents(fn () => User::factory()->create());
        $plans = DB::table('plans')->pluck('id', 'code');
        $now = now();

        DB::table('user_subscriptions')->insert([
            [
                'user_id' => $owner->id,
                'plan_id' => $plans['pro'],
                'status' => 'active',
                'source' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'user_id' => $freeUser->id,
                'plan_id' => $plans['free'],
                'status' => 'active',
                'source' => 'manual',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $linkId = DB::table('links')->insertGetId([
            'user_id' => $owner->id,
            'slug' => 'legacy-link',
            'destination_url' => 'https://example.com/legacy',
            'title' => 'Legacy link',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $bioPageId = DB::table('bio_pages')->insertGetId([
            'user_id' => $owner->id,
            'handle' => 'legacy-bio',
            'display_name' => 'Legacy bio',
            'is_published' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $folderId = DB::table('folders')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Legacy folder',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $domainId = DB::table('custom_domains')->insertGetId([
            'user_id' => $owner->id,
            'hostname' => 'legacy.example.test',
            'verification_token' => str_repeat('a', 40),
            'status' => 'pending_dns',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $pixelId = DB::table('tracking_pixels')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Legacy pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '123456789012345',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $linkPageId = DB::table('link_pages')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Legacy page',
            'slug' => 'legacy-page',
            'title' => 'Legacy page',
            'is_published' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $overlayId = DB::table('link_overlays')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Legacy overlay',
            'heading' => 'Legacy heading',
            'cta_label' => 'Continue',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $webhookId = DB::table('webhooks')->insertGetId([
            'user_id' => $owner->id,
            'name' => 'Legacy webhook',
            'url' => 'https://hooks.example.test/events',
            'secret' => 'encrypted-secret',
            'events' => json_encode(['link.clicked'], JSON_THROW_ON_ERROR),
            'revision' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $eventId = DB::table('analytics_events')->insertGetId([
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'user_id' => $owner->id,
            'link_id' => $linkId,
            'event_name' => 'link.clicked',
            'occurred_at' => $now,
            'visitor_hash' => str_repeat('b', 64),
            'visitor_key_version' => 'v1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $migration = require database_path('migrations/2026_10_19_001700_backfill_legacy_workspaces.php');
        $migration->up();

        $workspace = DB::table('workspaces')->where('personal_owner_user_id', $owner->id)->sole();
        $freeWorkspace = DB::table('workspaces')->where('personal_owner_user_id', $freeUser->id)->sole();

        $this->assertNull(
            DB::table('workspaces')->where('id', $workspace->id)->value('owner_id'),
            'The backfill must not write workspaces.owner_id; 2026_10_19_001900 derives it from the owner membership.',
        );
        $this->assertNull(
            DB::table('workspaces')->where('id', $freeWorkspace->id)->value('owner_id'),
            'The backfill must not write workspaces.owner_id; 2026_10_19_001900 derives it from the owner membership.',
        );

        $this->assertSame("personal-{$owner->id}", $workspace->slug);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $freeWorkspace->id,
            'user_id' => $freeUser->id,
            'role' => 'owner',
        ]);
        $this->assertSame($workspace->id, DB::table('users')->where('id', $owner->id)->value('current_workspace_id'));

        foreach ([
            'links' => $linkId,
            'bio_pages' => $bioPageId,
            'folders' => $folderId,
            'custom_domains' => $domainId,
            'tracking_pixels' => $pixelId,
            'link_pages' => $linkPageId,
            'link_overlays' => $overlayId,
            'webhooks' => $webhookId,
            'analytics_events' => $eventId,
        ] as $table => $id) {
            $this->assertSame($workspace->id, DB::table($table)->where('id', $id)->value('workspace_id'), $table);
            $this->assertSame($owner->id, DB::table($table)->where('id', $id)->value('user_id'), $table);
        }

        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'plan_id' => $plans['pro'],
            'status' => 'active',
            'source' => 'manual',
        ]);
        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $freeWorkspace->id,
            'plan_id' => $plans['free'],
            'status' => 'active',
            'source' => 'manual',
        ]);

        $teamWorkspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Shared workspace',
            'slug' => 'shared-workspace',
            'personal_owner_user_id' => null,
            'owner_id' => $owner->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $teamWorkspaceId,
            'user_id' => $owner->id,
            'role' => 'member',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('users')->where('id', $owner->id)->update(['current_workspace_id' => $teamWorkspaceId]);
        DB::table('users')->where('id', $freeUser->id)->update(['current_workspace_id' => $workspace->id]);

        $migration->up();

        $this->assertSame($teamWorkspaceId, DB::table('users')->where('id', $owner->id)->value('current_workspace_id'));
        $this->assertSame($freeWorkspace->id, DB::table('users')->where('id', $freeUser->id)->value('current_workspace_id'));
        $this->assertSame(3, DB::table('workspaces')->count());
        $this->assertSame(3, DB::table('workspace_user')->count());
        $this->assertSame(3, DB::table('workspace_subscriptions')->count());
    }
    public function test_contract_migration_refuses_unassigned_records_without_dropping_legacy_subscriptions(): void
    {
        $this->createLegacySubscriptionTable();
        $this->makeWorkspaceIdsNullable(['links']);
        $owner = User::factory()->create();
        Link::factory()->for($owner)->create([
            'workspace_id' => null,
            'slug' => 'unassigned-contract-link',
        ]);
        $now = now();
        DB::table('user_subscriptions')->insert([
            'user_id' => $owner->id,
            'plan_id' => DB::table('plans')->where('code', 'free')->value('id'),
            'status' => 'active',
            'source' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $migration = require database_path('migrations/2026_10_19_001800_contract_workspace_ownership.php');

        try {
            $migration->up();
            $this->fail('Expected the contract migration to reject records without a workspace.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('without a workspace', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('user_subscriptions'));
        $this->assertDatabaseHas('user_subscriptions', ['user_id' => $owner->id]);
        $this->assertDatabaseHas('links', ['slug' => 'unassigned-contract-link', 'workspace_id' => null]);
    }

    public function test_contract_migration_drops_legacy_subscriptions_and_down_restores_them_exactly(): void
    {
        $this->createLegacySubscriptionTable();
        $owner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->firstOrFail();
        $link = Link::factory()->for($owner)->create(['slug' => 'contract-link']);
        $proPlanId = DB::table('plans')->where('code', 'pro')->value('id');
        $createdAt = now()->subDays(3)->toDateTimeString();
        $updatedAt = now()->subDay()->toDateTimeString();

        DB::table('workspace_subscriptions')->where('workspace_id', $workspace->id)->update([
            'plan_id' => $proPlanId,
            'status' => 'active',
            'source' => 'manual',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
        DB::table('user_subscriptions')->insert([
            'user_id' => $owner->id,
            'plan_id' => $proPlanId,
            'status' => 'active',
            'source' => 'manual',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        $migration = require database_path('migrations/2026_10_19_001800_contract_workspace_ownership.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('user_subscriptions'));
        $migration->down();

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $owner->id,
            'plan_id' => $proPlanId,
            'status' => 'active',
            'source' => 'manual',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
        $this->assertSame($workspace->id, DB::table('links')->where('id', $link->id)->value('workspace_id'));
    }

    public function test_expand_migration_adds_workspace_ownership_without_removing_creator_attribution(): void
    {
        foreach ([
            'links',
            'bio_pages',
            'folders',
            'custom_domains',
            'tracking_pixels',
            'link_pages',
            'link_overlays',
            'webhooks',
            'analytics_events',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'workspace_id'), $table);
            $this->assertTrue(Schema::hasColumn($table, 'user_id'), $table);
        }

        $this->assertTrue(Schema::hasColumn('users', 'current_workspace_id'));
        $this->assertTrue(Schema::hasTable('workspaces'));
        $this->assertTrue(Schema::hasTable('workspace_user'));
        $this->assertTrue(Schema::hasTable('workspace_invitations'));
        $this->assertTrue(Schema::hasTable('workspace_subscriptions'));
        $this->assertTrue(Schema::hasTable('workspace_audits'));
        $this->assertTrue(Schema::hasColumn('plans', 'member_limit'));
    }

    public function test_member_capacity_is_defined_for_each_plan(): void
    {
        $limits = DB::table('plans')->pluck('member_limit', 'code');

        $this->assertSame(1, $limits['free']);
        $this->assertSame(5, $limits['pro']);
        $this->assertNull($limits['business']);
    }

    public function test_expand_rollback_refuses_to_remove_team_workspace_data(): void
    {
        $user = User::factory()->create();
        $now = now();
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Team workspace',
            'slug' => 'team-workspace',
            'personal_owner_user_id' => null,
            'owner_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $migration = require database_path('migrations/2026_10_19_001600_expand_workspace_ownership.php');

        try {
            $migration->down();
            $this->fail('Expected rollback to refuse team workspace data loss.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Workspace ownership cannot be rolled back after collaboration or non-owner data has been created.',
                $exception->getMessage(),
            );
        }

        $this->assertTrue(Schema::hasTable('workspaces'));
        $this->assertTrue(Schema::hasColumn('links', 'workspace_id'));
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_expand_rollback_refuses_a_personal_workspace_after_owner_transfer(): void
    {
        $originalOwner = User::factory()->create();
        $newOwner = User::factory()->create();
        $workspace = $originalOwner->personalWorkspace()->firstOrFail();
        $workspaceId = $workspace->getKey();
        $now = now();
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $newOwner->id,
            'role' => 'member',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $originalOwner->id)
            ->update(['role' => 'member', 'updated_at' => $now]);
        DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $newOwner->id)
            ->update(['role' => 'owner', 'updated_at' => $now]);
        DB::table('workspaces')->where('id', $workspaceId)->update([
            'personal_owner_user_id' => null,
            'owner_id' => $newOwner->id,
            'slug' => 'transferred-workspace',
            'updated_at' => $now,
        ]);
        $migration = require database_path('migrations/2026_10_19_001600_expand_workspace_ownership.php');

        try {
            $migration->down();
            $this->fail('Expected rollback to refuse a transferred personal workspace.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Workspace ownership cannot be rolled back after collaboration or non-owner data has been created.',
                $exception->getMessage(),
            );
        }

        $this->assertTrue(Schema::hasTable('workspaces'));
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspaceId,
            'user_id' => $newOwner->id,
            'role' => 'owner',
        ]);
    }
    private function createLegacySubscriptionTable(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('source', 20);
            $table->timestamps();
        });
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function makeWorkspaceIdsNullable(array $tables = [
        'links',
        'bio_pages',
        'folders',
        'custom_domains',
        'tracking_pixels',
        'link_pages',
        'link_overlays',
        'webhooks',
        'analytics_events',
    ]): void {
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable()->change();
            });
        }
    }

    /**
     * workspaces.owner_id does not exist at the 2026_10_19_001700 chronology
     * point; relax it so the legacy backfill runs against its real schema.
     */
    private function makeWorkspaceOwnerIdNullable(): void
    {
        Schema::table('workspaces', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_id')->nullable()->change();
        });
    }
}
