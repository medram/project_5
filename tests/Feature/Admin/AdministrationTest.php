<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\User;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_administrators_cannot_view_administration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_super_administrator_sees_metadata_without_private_content(): void
    {
        $administrator = User::factory()->admin()->create();
        $account = User::factory()->create();
        $link = $account->personalWorkspace()->firstOrFail()->links()->create([
            'user_id' => $account->id,
            'slug' => 'admin-private-link',
            'destination_url' => 'https://private-destination.example/hidden',
            'is_active' => true,
        ]);

        $this->withoutVite();

        $response = $this->actingAs($administrator)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/index')
                ->has('users', 2)
                ->where('auth.isAdmin', true)
                ->where('users.1.id', $account->id)
                ->where('users.1.isAdmin', false)
                ->where('users.1.roles', [])
                ->where('users.0.roles.0.code', 'super_admin')
                ->where('users.1.subscription.planCode', 'free')
                ->where('users.1.counts.links', 1)
                ->missing('users.1.links')
                ->missing('users.1.destinationUrl'));

        $response->assertDontSee($link->destination_url);
    }

    public function test_support_role_reads_administration_but_cannot_use_privileged_mutations(): void
    {
        $support = User::factory()->support()->create();
        $account = User::factory()->create();
        $pro = Plan::query()->where('code', 'pro')->sole();

        $this->withoutVite();
        $this->actingAs($support)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.isAdmin', true)
                ->where('auth.canSearchPlatform', true)
                ->where('auth.adminAbilities', fn ($abilities) => in_array(PlatformPermission::AdminUsersView->value, collect($abilities)->all(), true)
                    && ! in_array(PlatformPermission::AdminUsersManageRoles->value, collect($abilities)->all(), true)));

        $this->actingAs($support)
            ->patch("/admin/users/{$account->id}/plan", ['plan' => $pro->code])
            ->assertForbidden();
        $this->actingAs($support)
            ->patch("/admin/users/{$account->id}/administrator", ['is_admin' => true])
            ->assertForbidden();
        $this->actingAs($support)
            ->post('/admin/notifications/broadcast', ['title' => 'No', 'body' => 'No'])
            ->assertForbidden();

        $this->actingAs($support)
            ->patch("/admin/users/{$account->id}/suspension", ['suspended' => true])
            ->assertRedirect('/admin');
        $this->assertTrue($account->refresh()->is_suspended);
    }

    public function test_role_assignment_grants_and_revokes_without_touching_legacy_flag(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->patch("/admin/users/{$target->id}/administrator", ['is_admin' => true])
            ->assertRedirect('/admin');

        $this->assertTrue($target->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));
        $this->assertFalse($target->is_admin);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $actor->id,
            'permission' => PlatformPermission::AdminUsersManageRoles->value,
            'action' => 'grant_role',
            'subject_id' => $target->id,
        ]);

        $this->actingAs($actor)
            ->patch("/admin/users/{$target->id}/administrator", ['is_admin' => false])
            ->assertRedirect('/admin');

        $this->assertFalse($target->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'revoke_role', 'subject_id' => $target->id]);
    }

    public function test_self_role_changes_and_removing_the_last_super_administrator_are_rejected(): void
    {
        $actor = User::factory()->admin()->create();

        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/administrator", ['is_admin' => false])
            ->assertUnprocessable();

        $target = User::factory()->admin()->create();
        $this->assertTrue($actor->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));

        $this->actingAs($actor)
            ->patch("/admin/users/{$target->id}/administrator", ['is_admin' => false])
            ->assertRedirect('/admin');
        $this->assertFalse($target->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));

        $this->assertTrue(app(PlatformAuthorization::class)->isLastActiveSuperAdmin($actor->refresh()));

        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/administrator", ['is_admin' => false])
            ->assertUnprocessable();
        $this->assertTrue($actor->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));
    }

    public function test_administrations_cannot_suspend_themselves_or_the_last_super_administrator(): void
    {
        $actor = User::factory()->admin()->create();
        $peer = User::factory()->admin()->create();

        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/suspension", ['suspended' => true])
            ->assertUnprocessable();

        $this->actingAs($actor)
            ->patch("/admin/users/{$peer->id}/suspension", ['suspended' => true])
            ->assertRedirect('/admin');
        $this->assertTrue($peer->refresh()->is_suspended);

        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/suspension", ['suspended' => true])
            ->assertUnprocessable();

        $this->assertFalse($actor->refresh()->is_suspended);
    }

    public function test_suspension_clears_sessions_and_blocks_authenticated_surfaces(): void
    {
        $administrator = User::factory()->admin()->create();
        $account = User::factory()->create(['remember_token' => 'remember-me']);

        DB::table('sessions')->insert([
            'id' => 'suspended-account-session',
            'user_id' => $account->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/suspension", ['suspended' => true])
            ->assertRedirect('/admin');

        $account->refresh();

        $this->assertTrue($account->is_suspended);
        $this->assertNull($account->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'suspended-account-session']);
        $this->assertDatabaseHas('admin_audit_logs', [
            'permission' => PlatformPermission::AdminUsersManageStatus->value,
            'action' => 'update_suspension',
            'subject_id' => $account->id,
        ]);

        $this->actingAs($account)->get('/dashboard')->assertForbidden();
    }

    public function test_suspension_revalidates_the_actor_inside_the_transaction(): void
    {
        $administrator = User::factory()->admin()->create();
        $account = User::factory()->create();

        $demoted = false;
        Event::listen(TransactionBeginning::class, function () use ($administrator, &$demoted): void {
            if ($demoted) {
                return;
            }

            $demoted = true;
            DB::table('platform_role_user')->where('user_id', $administrator->id)->delete();
        });

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/suspension", ['suspended' => true])
            ->assertForbidden();

        $this->assertTrue($demoted);
        $this->assertFalse($account->refresh()->is_suspended);
    }

    public function test_role_change_revalidates_the_actor_inside_the_transaction(): void
    {
        $administrator = User::factory()->admin()->create();
        $account = User::factory()->create();

        $demoted = false;
        Event::listen(TransactionBeginning::class, function () use ($administrator, &$demoted): void {
            if ($demoted) {
                return;
            }

            $demoted = true;
            DB::table('platform_role_user')->where('user_id', $administrator->id)->delete();
        });

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/administrator", ['is_admin' => true])
            ->assertForbidden();

        $this->assertTrue($demoted);
        $this->assertFalse($account->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));
    }

    public function test_audit_records_are_append_only_and_redact_sensitive_keys(): void
    {
        $actor = User::factory()->admin()->create();
        $account = User::factory()->create();
        $pro = Plan::query()->where('code', 'pro')->sole();

        $this->actingAs($actor)->patch("/admin/users/{$account->id}/plan", ['plan' => $pro->code]);
        $this->actingAs($actor)->patch("/admin/users/{$account->id}/suspension", ['suspended' => true]);

        $record = app(AdminAudit::class)->record(
            $actor,
            PlatformPermission::AdminUsersView,
            'probe',
            $account,
            " reason\nwith control ",
            [
                'password' => 'plain-text',
                'ip_address' => '203.0.113.7',
                'destination_url' => 'https://private.example/hidden',
                'nested' => ['token' => 'abc', 'safe' => 'value'],
            ],
            'req-123',
        );

        $this->assertSame('[redacted]', $record->changes['password']);
        $this->assertSame('[redacted]', $record->changes['ip_address']);
        $this->assertSame('[redacted]', $record->changes['destination_url']);
        $this->assertSame('[redacted]', $record->changes['nested']['token']);
        $this->assertSame('value', $record->changes['nested']['safe']);
        $this->assertSame('req-123', $record->correlation_id);
        $this->assertStringNotContainsString("\n", (string) $record->reason);
        $this->assertDatabaseHas('admin_audit_logs', ['permission' => PlatformPermission::AdminBillingManage->value]);
        $this->assertDatabaseHas('admin_audit_logs', ['permission' => PlatformPermission::AdminUsersManageStatus->value]);

        try {
            $record->forceFill(['action' => 'tampered'])->save();
            $this->fail('Audit updates must be rejected.');
        } catch (LogicException) {
            $this->assertSame('probe', $record->fresh()->action);
        }

        try {
            $record->delete();
            $this->fail('Audit deletes must be rejected.');
        } catch (LogicException) {
            $this->assertDatabaseHas('admin_audit_logs', ['id' => $record->id]);
        }
    }

    public function test_platform_role_rows_are_immutable(): void
    {
        $role = PlatformRole::query()->where('code', PlatformRoleCode::SuperAdmin->value)->sole();

        try {
            $role->update(['name' => 'Changed']);
            $this->fail('Platform role updates must be rejected.');
        } catch (LogicException) {
            $this->assertSame('Super administrator', $role->fresh()->name);
        }

        try {
            $role->delete();
            $this->fail('Platform role deletes must be rejected.');
        } catch (LogicException) {
            $this->assertDatabaseHas('platform_roles', ['code' => PlatformRoleCode::SuperAdmin->value]);
        }
    }

    public function test_grant_administrator_command_assigns_the_immutable_role(): void
    {
        $user = User::factory()->create();

        $this->artisan('linkpilot:grant-admin', ['email' => $user->email])
            ->expectsOutputToContain($user->email)
            ->assertExitCode(0);

        $this->assertTrue($user->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));
        $this->assertFalse($user->is_admin);
        $this->assertSame(['super_admin'], PlatformRole::query()->whereHas('users', fn ($query) => $query->whereKey($user->id))->pluck('code')->all());
    }

    public function test_grant_administrator_command_does_not_create_a_missing_account(): void
    {
        $email = 'missing-administrator@example.test';

        $this->artisan('linkpilot:grant-admin', ['email' => $email])
            ->expectsOutputToContain('No LinkPilot account exists')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }
}
