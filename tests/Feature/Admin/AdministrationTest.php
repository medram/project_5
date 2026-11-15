<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRoleCode;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\User;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_administrators_cannot_view_administration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_root_redirects_to_the_first_granted_destination(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertRedirect('/admin/users');

        $this->actingAs(User::factory()->support()->create())
            ->get('/admin')
            ->assertRedirect('/admin/users');
    }

    public function test_users_index_exposes_safe_metadata_and_support_abilities(): void
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
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->where('auth.isAdmin', true)
                ->has('users.data', 2)
                ->where('users.data.1.id', $account->id)
                ->where('users.data.1.emailVerified', true)
                ->where('users.data.1.plan.code', 'free')
                ->where('users.data.1.personalWorkspace.linksCount', 1)
                ->missing('users.data.1.destinationUrl')
                ->has('filters')
                ->has('plans')
                ->has('roles'));

        $response->assertDontSee($link->destination_url);
    }

    public function test_support_reads_users_but_cannot_reach_roles_or_privileged_mutations(): void
    {
        $support = User::factory()->support()->create();
        $account = User::factory()->create();
        $pro = Plan::query()->where('code', 'pro')->sole();

        $this->withoutVite();
        $this->actingAs($support)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.adminAbilities', fn ($abilities) => in_array(
                    PlatformPermission::AdminUsersView->value,
                    collect($abilities)->all(),
                    true,
                ))
                ->where('auth.adminAbilities', fn ($abilities) => ! in_array(
                    PlatformPermission::AdminUsersManageRoles->value,
                    collect($abilities)->all(),
                    true,
                )));

        $this->actingAs($support)->get('/admin/roles')->assertForbidden();
        $this->actingAs($support)
            ->patch("/admin/users/{$account->id}/plan", ['plan' => $pro->code])
            ->assertForbidden();
        $this->actingAs($support)
            ->patch("/admin/users/{$account->id}/roles", ['role' => 'support', 'assigned' => true])
            ->assertForbidden();
        $this->actingAs($support)
            ->patch("/admin/users/{$account->id}/verification", ['verified' => false])
            ->assertForbidden();
        $this->actingAs($support)
            ->post('/admin/notifications/broadcast', ['title' => 'No', 'body' => 'No'])
            ->assertForbidden();

        $this->actingAs($support)
            ->patch("/admin/users/{$account->id}/suspension", ['suspended' => true])
            ->assertRedirect("/admin/users/{$account->id}");
        $this->assertTrue($account->refresh()->is_suspended);
    }

    public function test_users_filters_search_status_verification_role_and_plan(): void
    {
        $administrator = User::factory()->admin()->create(['name' => 'Platform Administrator']);
        $unverified = User::factory()->unverified()->create(['name' => 'Needle Unverified']);
        $suspended = User::factory()->create(['name' => 'Needle Suspended', 'is_suspended' => true]);
        $support = User::factory()->support()->create(['name' => 'Needle Support']);
        $pro = Plan::query()->where('code', 'pro')->sole();
        $proUser = User::factory()->create(['name' => 'Needle Pro']);
        $freeUser = User::factory()->create(['name' => 'Needle Free']);
        app(WorkspaceUsage::class)->assignPlan($proUser->personalWorkspace()->firstOrFail(), $pro);

        $this->withoutVite();

        $this->actingAs($administrator)
            ->get('/admin/users?q=Needle&verification=unverified')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $unverified->id));

        $this->actingAs($administrator)
            ->get('/admin/users?status=suspended')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $suspended->id));

        $this->actingAs($administrator)
            ->get('/admin/users?role=support')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $support->id));

        $this->actingAs($administrator)
            ->get('/admin/users?plan=pro')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $proUser->id)
                ->where('users.data.0.plan.code', 'pro'));

        $this->actingAs($administrator)
            ->get('/admin/users?q=Needle Free&plan=free')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $freeUser->id)
                ->where('users.data.0.plan.code', 'free'));

        $this->actingAs($administrator)
            ->get('/admin/users?role=none')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data', fn ($users) => collect($users)->every(
                    fn ($user) => $user['roles'] === [],
                )));
    }

    public function test_user_list_paginates_twenty_accounts_per_page(): void
    {
        $administrator = User::factory()->admin()->create();
        User::factory()->count(22)->create();

        $this->withoutVite();
        $this->actingAs($administrator)
            ->get('/admin/users?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.current_page', 2)
                ->where('users.per_page', 20)
                ->has('users.data', 3));
    }

    public function test_user_detail_exposes_sections_without_private_content(): void
    {
        $administrator = User::factory()->admin()->create();
        $account = User::factory()->create();
        $account->personalWorkspace()->firstOrFail()->links()->create([
            'user_id' => $account->id,
            'slug' => 'detail-private-link',
            'destination_url' => 'https://detail-private.example/path',
            'is_active' => true,
        ]);

        $this->withoutVite();

        $response = $this->actingAs($administrator)
            ->get("/admin/users/{$account->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/show')
                ->where('account.id', $account->id)
                ->where('account.isSelf', false)
                ->has('security')
                ->has('workspaces', 1)
                ->where('workspaces.0.linksCount', 1)
                ->has('roles', 2)
                ->has('plans')
                ->missing('workspaces.0.destinationUrl'));

        $response->assertDontSee('detail-private.example');
    }

    public function test_suspension_verification_session_and_token_actions_are_audited(): void
    {
        Notification::fake();
        $administrator = User::factory()->admin()->create();
        $account = User::factory()->unverified()->create();
        $account->createWorkspaceApiToken($account->personalWorkspace()->firstOrFail(), 'ci-token', ['*']);
        DB::table('sessions')->insert([
            'id' => 'admin-action-session',
            'user_id' => $account->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/verification", ['verified' => true])
            ->assertRedirect("/admin/users/{$account->id}");
        $this->assertNotNull($account->refresh()->email_verified_at);

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/verification", ['verified' => false])
            ->assertRedirect("/admin/users/{$account->id}");
        $this->assertNull($account->refresh()->email_verified_at);

        $this->actingAs($administrator)
            ->post("/admin/users/{$account->id}/verification/resend")
            ->assertRedirect("/admin/users/{$account->id}");
        Notification::assertSentTo($account, VerifyEmail::class);

        $this->actingAs($administrator)
            ->delete("/admin/users/{$account->id}/sessions")
            ->assertRedirect("/admin/users/{$account->id}");
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-action-session']);

        $this->actingAs($administrator)
            ->delete("/admin/users/{$account->id}/tokens")
            ->assertRedirect("/admin/users/{$account->id}");
        $this->assertDatabaseCount('personal_access_tokens', 0);

        foreach ([
            'verify_email',
            'unverify_email',
            'resend_verification',
            'revoke_sessions',
            'revoke_tokens',
        ] as $action) {
            $this->assertDatabaseHas('admin_audit_logs', [
                'action' => $action,
                'subject_id' => $account->id,
            ]);
        }
    }

    public function test_role_deletion_and_plan_actions_are_audited_and_preserve_filters(): void
    {
        $administrator = User::factory()->admin()->create();
        $account = User::factory()->create(['name' => 'Needle Action']);
        $pro = Plan::query()->where('code', 'pro')->sole();

        $this->from('/admin/users?q=Needle')
            ->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/roles", [
                'role' => PlatformRoleCode::Support->value,
                'assigned' => true,
            ])
            ->assertRedirect('/admin/users?q=Needle');
        $this->assertTrue($account->refresh()->hasPlatformRole(PlatformRoleCode::Support));

        $this->from('/admin/users?q=Needle')
            ->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/plan", ['plan' => $pro->code])
            ->assertRedirect('/admin/users?q=Needle');
        $this->assertSame(
            $pro->id,
            app(WorkspaceUsage::class)
                ->effectivePlanFor($account->personalWorkspace()->firstOrFail())['plan']->id,
        );

        $this->from('/admin/users?q=Needle')
            ->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/deletion")
            ->assertRedirect('/admin/users?q=Needle');
        $this->assertNotNull($account->refresh()->deletion_requested_at);

        $this->from('/admin/users?q=Needle')
            ->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/deletion/restore")
            ->assertRedirect('/admin/users?q=Needle');
        $this->assertNull($account->refresh()->deletion_requested_at);

        foreach (['grant_role', 'assign_plan', 'schedule_deletion', 'restore_deletion'] as $action) {
            $this->assertDatabaseHas('admin_audit_logs', [
                'action' => $action,
                'subject_id' => $account->id,
            ]);
        }
    }

    public function test_self_mutations_and_last_super_administrator_are_protected(): void
    {
        $actor = User::factory()->admin()->create();
        $peer = User::factory()->admin()->create();

        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/roles", ['role' => 'super_admin', 'assigned' => false])
            ->assertRedirect()
            ->assertSessionHasErrors('role');
        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/suspension", ['suspended' => true])
            ->assertUnprocessable();

        $this->actingAs($actor)
            ->patch("/admin/users/{$peer->id}/roles", ['role' => 'super_admin', 'assigned' => false])
            ->assertRedirect("/admin/users/{$peer->id}");
        $this->assertFalse($peer->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));

        $this->actingAs($actor)
            ->patch("/admin/users/{$actor->id}/suspension", ['suspended' => true])
            ->assertUnprocessable();
        $this->assertTrue($actor->refresh()->hasPlatformRole(PlatformRoleCode::SuperAdmin));
        $this->assertTrue(app(PlatformAuthorization::class)->isLastActiveSuperAdmin($actor));
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
            ->patch("/admin/users/{$account->id}/roles", ['role' => 'support', 'assigned' => true])
            ->assertForbidden();

        $this->assertTrue($demoted);
        $this->assertFalse($account->refresh()->hasPlatformRole(PlatformRoleCode::Support));
    }

    public function test_roles_page_is_a_read_only_matrix_with_members(): void
    {
        $administrator = User::factory()->admin()->create();
        User::factory()->support()->create(['name' => 'Support Member']);

        $this->withoutVite();
        $this->actingAs($administrator)
            ->get('/admin/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/roles/index')
                ->has('roles', 2)
                ->where('roles.0.code', 'super_admin')
                ->where('roles.0.usersCount', 1)
                ->where('roles.1.code', 'support')
                ->where('roles.1.usersCount', 1)
                ->where('roles.1.members.0.name', 'Support Member')
                ->has('roles.0.permissions'));
    }

    public function test_platform_search_returns_only_authorized_result_types(): void
    {
        $administrator = User::factory()->admin()->create();
        $support = User::factory()->support()->create();
        User::factory()->create(['name' => 'Needle Search Account']);
        Plan::query()->where('code', 'pro')->update(['name' => 'Needle Plan']);

        $this->actingAs($administrator)
            ->getJson(route('search.index', ['q' => 'Needle'], false))
            ->assertOk()
            ->assertJsonFragment(['type' => 'account'])
            ->assertJsonFragment(['type' => 'plan'])
            ->assertJsonFragment(['href' => '/admin/users?q=Needle']);

        $this->actingAs($support)
            ->getJson(route('search.index', ['q' => 'Needle'], false))
            ->assertOk()
            ->assertJsonFragment(['type' => 'account'])
            ->assertJsonMissing(['type' => 'plan']);
    }

    public function test_audit_records_are_append_only_and_redact_sensitive_keys(): void
    {
        $actor = User::factory()->admin()->create();
        $account = User::factory()->create();

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
        $this->assertFalse(Schema::hasColumn('users', 'is_admin'));
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
