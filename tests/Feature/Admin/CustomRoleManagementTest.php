<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\User;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CustomRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_role_assignment_and_permission_changes_take_effect_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'QR editor',
            'code' => 'qr_editor',
            'permissions' => ['admin.access', 'admin.content.qr_codes.view', 'admin.content.qr_codes.manage'],
        ])->assertRedirect();

        $this->actingAs($admin)->patch("/admin/users/{$member->id}/roles", [
            'role' => 'qr_editor', 'assigned' => true,
        ])->assertRedirect();

        $authorization = app(PlatformAuthorization::class);
        $this->assertContains('admin.content.qr_codes.manage', $authorization->abilities($member));
        $this->actingAs($member)->get('/admin/links')->assertForbidden();
        $this->actingAs($member)->get('/admin/plans')->assertForbidden();

        $this->actingAs($admin)->patch('/admin/roles/qr_editor', [
            'name' => 'QR editor',
            'permissions' => ['admin.access', 'admin.content.qr_codes.view'],
        ])->assertRedirect();
        $this->assertNotContains('admin.content.qr_codes.manage', $authorization->abilities($member));
        $this->assertContains('admin.content.qr_codes.view', $authorization->abilities($member));
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'action' => 'grant_role']);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'action' => 'update_role']);
        $removed = DB::table('admin_audit_logs')->where('action', 'remove_role_permission')->sole();
        $this->assertSame($admin->id, $removed->actor_user_id);
        $this->assertSame([
            'role' => 'qr_editor',
            'permission' => 'admin.content.qr_codes.manage',
            'affected_user_count' => 1,
        ], json_decode($removed->changes, true));
    }

    public function test_roles_are_validated_immutable_when_seeded_and_not_deleted_with_members(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        foreach (['none', 'super_admin', 'support', 'UPPER'] as $code) {
            $this->post('/admin/roles', ['name' => 'Invalid', 'code' => $code, 'permissions' => []])
                ->assertSessionHasErrors('code');
        }
        $this->post('/admin/roles', [
            'name' => 'Invalid', 'code' => 'invalid_permissions',
            'permissions' => ['admin.access', 'admin.access'],
        ])->assertSessionHasErrors('permissions.1');
        $this->post('/admin/roles', [
            'name' => 'Invalid', 'code' => 'invalid_permissions',
            'permissions' => ['admin.unknown'],
        ])->assertSessionHasErrors('permissions.0');
        $this->patch('/admin/roles/support', ['name' => 'Changed', 'permissions' => []])->assertForbidden();
        $this->delete('/admin/roles/support')->assertForbidden();

        $this->post('/admin/roles', ['name' => 'Reader', 'code' => 'reader', 'permissions' => ['admin.access']])->assertRedirect();
        $member = User::factory()->create();
        $this->patch("/admin/users/{$member->id}/roles", ['role' => 'reader', 'assigned' => true])->assertRedirect();
        $this->delete('/admin/roles/reader')->assertSessionHasErrors('role');
        $this->assertTrue($member->hasPlatformRole('reader'));
        $this->assertDatabaseHas('platform_roles', ['code' => 'reader']);
    }

    public function test_delegation_respects_actor_ceiling_target_union_and_self_protection(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create();
        $target = User::factory()->support()->create();
        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Manager', 'code' => 'manager',
            'permissions' => ['admin.access', 'admin.users.view', 'admin.users.manage_roles'],
        ])->assertRedirect();
        $this->actingAs($admin)->patch("/admin/users/{$manager->id}/roles", [
            'role' => 'manager', 'assigned' => true,
        ])->assertRedirect();

        $this->actingAs($manager)->post('/admin/roles', [
            'name' => 'Escalation', 'code' => 'escalation', 'permissions' => ['admin.billing.manage'],
        ])->assertForbidden();
        $this->actingAs($manager)->patch('/admin/roles/manager', [
            'name' => 'Changed', 'permissions' => ['admin.access'],
        ])->assertForbidden();
        $this->actingAs($manager)->patch("/admin/users/{$manager->id}/roles", [
            'role' => 'manager', 'assigned' => false,
        ])->assertSessionHasErrors('role');
        $this->actingAs($manager)->patch("/admin/users/{$target->id}/roles", [
            'role' => 'manager', 'assigned' => true,
        ])->assertForbidden();
        $this->assertFalse($target->hasPlatformRole('manager'));
    }

    public function test_multiple_roles_union_only_recognized_permissions_and_inactive_users_have_none(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Links', 'code' => 'links_reader', 'permissions' => ['admin.content.links.view'],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Access', 'code' => 'access_reader', 'permissions' => ['admin.access'],
        ])->assertRedirect();
        foreach (['links_reader', 'access_reader'] as $role) {
            $this->actingAs($admin)->patch("/admin/users/{$member->id}/roles", ['role' => $role, 'assigned' => true])->assertRedirect();
        }
        $role = PlatformRole::query()->where('code', 'access_reader')->sole();
        DB::table('platform_role_permissions')->insert(['platform_role_id' => $role->id, 'permission' => 'admin.unknown']);
        $this->withoutVite()->actingAs($member)->get('/admin/links')->assertOk();
        $this->assertNotContains('admin.unknown', app(PlatformAuthorization::class)->abilities($member));
        $member->forceFill(['is_suspended' => true])->save();
        $this->assertSame([], app(PlatformAuthorization::class)->abilities($member->fresh()));
    }

    public function test_custom_role_filter_and_effective_administrator_count(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['name' => 'Custom Reader']);
        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Reader', 'code' => 'reader', 'permissions' => ['admin.access'],
        ])->assertRedirect();
        $this->actingAs($admin)->patch("/admin/users/{$member->id}/roles", ['role' => 'reader', 'assigned' => true])->assertRedirect();
        $this->withoutVite()->actingAs($admin)->get('/admin/users?role=reader')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('users.total', 1)
                ->where('users.data.0.name', 'Custom Reader')
                ->where('overview.administrators', 2));
    }

    public function test_grants_require_admin_access_and_suspended_accounts_cannot_receive_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Links only', 'code' => 'links_only',
            'permissions' => ['admin.content.links.view'],
        ])->assertRedirect();
        $this->actingAs($admin)->patch("/admin/users/{$member->id}/roles", [
            'role' => 'links_only', 'assigned' => true,
        ])->assertRedirect();
        $this->actingAs($member)->get('/admin/links')->assertForbidden();

        $suspended = User::factory()->create(['is_suspended' => true]);
        $this->actingAs($admin)->patch("/admin/users/{$suspended->id}/roles", [
            'role' => 'links_only', 'assigned' => true,
        ])->assertSessionHasErrors('role');
        $this->assertFalse($suspended->hasPlatformRole('links_only'));
    }

    public function test_only_a_super_admin_can_grant_or_revoke_super_admin_even_with_every_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create();
        $target = User::factory()->create();
        $permissions = array_map(fn (PlatformPermission $permission) => $permission->value, PlatformPermission::cases());
        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Full manager', 'code' => 'full_manager', 'permissions' => $permissions,
        ])->assertRedirect();
        $this->actingAs($admin)->patch("/admin/users/{$manager->id}/roles", [
            'role' => 'full_manager', 'assigned' => true,
        ])->assertRedirect();
        $this->actingAs($manager)->patch("/admin/users/{$target->id}/roles", [
            'role' => 'super_admin', 'assigned' => true,
        ])->assertForbidden();
        $this->actingAs($manager)->patch("/admin/users/{$admin->id}/roles", [
            'role' => 'super_admin', 'assigned' => false,
        ])->assertForbidden();
        $this->assertFalse($target->hasPlatformRole('super_admin'));
        $this->assertTrue($admin->hasPlatformRole('super_admin'));
    }

    public function test_role_permission_rollback_refuses_to_discard_custom_roles(): void
    {
        PlatformRole::factory()->create();
        $migration = require database_path('migrations/2026_11_22_000001_create_platform_role_permissions_table.php');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('custom platform roles');
        $migration->down();
    }

    public function test_concurrent_super_admin_revocations_leave_one_active_administrator_on_sqlite(): void
    {
        $directory = sys_get_temp_dir().'/role-race-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $database = $directory.'/race.sqlite';
        $pdo = new \PDO('sqlite:'.$database);
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, is_suspended INTEGER NOT NULL DEFAULT 0, deletion_requested_at TEXT);
            CREATE TABLE platform_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, created_at TEXT, updated_at TEXT);
            CREATE TABLE platform_role_user (id INTEGER PRIMARY KEY, platform_role_id INTEGER NOT NULL, user_id INTEGER NOT NULL, created_at TEXT, updated_at TEXT);
            CREATE TABLE admin_audit_logs (id INTEGER PRIMARY KEY, actor_user_id INTEGER, permission TEXT, action TEXT, subject_type TEXT, subject_id INTEGER, correlation_id TEXT, reason TEXT, changes TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
            INSERT INTO users (id) VALUES (1), (2);
            INSERT INTO platform_roles (id, code, name) VALUES (1, 'super_admin', 'Super administrator');
            INSERT INTO platform_role_user (platform_role_id, user_id) VALUES (1, 1), (1, 2);
        SQL);
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $argv[1], 'database.connections.sqlite.url' => null]);
            Illuminate\Support\Facades\DB::purge('sqlite');
            Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout=5000');
            $actor = App\Models\User::query()->findOrFail((int) $argv[3]);
            $target = App\Models\User::query()->findOrFail((int) $argv[4]);
            $role = App\Models\PlatformRole::query()->where('code', 'super_admin')->firstOrFail();
            file_put_contents($argv[2].'/ready-'.$argv[3], 'ready');
            $deadline = microtime(true) + 10;
            while (!file_exists($argv[2].'/start') && microtime(true) < $deadline) { usleep(10000); }
            try {
                echo app(App\Services\Platform\PlatformRoleAssignments::class)->revoke($actor, $target, $role) ? 'revoked' : 'unchanged';
            } catch (Throwable $exception) {
                echo 'denied:'.get_class($exception);
            }
        PHP;
        $environment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => ''];
        $first = new Process([PHP_BINARY, '-r', $script, $database, $directory, '1', '2'], base_path(), $environment, timeout: 20);
        $second = new Process([PHP_BINARY, '-r', $script, $database, $directory, '2', '1'], base_path(), $environment, timeout: 20);

        try {
            $first->start();
            $second->start();
            $deadline = microtime(true) + 10;
            while ((! is_file($directory.'/ready-1') || ! is_file($directory.'/ready-2')) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertFileExists($directory.'/ready-1', $first->getErrorOutput());
            $this->assertFileExists($directory.'/ready-2', $second->getErrorOutput());
            touch($directory.'/start');
            $first->wait();
            $second->wait();
            $outputs = [$first->getOutput(), $second->getOutput()];
            $this->assertContains('revoked', $outputs);
            $this->assertContains('denied:Symfony\\Component\\HttpKernel\\Exception\\HttpException', $outputs);
            $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM platform_role_user')->fetchColumn());
        } finally {
            $first->stop();
            $second->stop();
            $pdo = null;
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
