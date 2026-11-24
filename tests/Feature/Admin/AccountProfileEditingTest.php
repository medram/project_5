<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class AccountProfileEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_email_clears_verification_notifies_and_audits_only_field_names(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($admin)->patch("/admin/users/{$target->id}/profile", [
            'name' => 'Updated Name', 'email' => 'new@example.com',
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('new@example.com', $target->email);
        $this->assertNull($target->email_verified_at);
        Notification::assertSentTo($target, VerifyEmail::class);
        $audit = DB::table('admin_audit_logs')->where('actor_user_id', $admin->id)->where('action', 'update_profile')->sole();
        $this->assertSame($target->id, $audit->subject_id);
        $this->assertStringContainsString('name', $audit->changes);
        $this->assertStringContainsString('email', $audit->changes);
        $this->assertStringNotContainsString('old@example.com', $audit->changes);
        $this->assertStringNotContainsString('new@example.com', $audit->changes);
    }

    public function test_unchanged_email_preserves_verification_and_sends_no_notification(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $verifiedAt = $target->email_verified_at->toISOString();

        $this->actingAs($admin)->patch("/admin/users/{$target->id}/profile", [
            'name' => 'Renamed', 'email' => $target->email,
        ])->assertRedirect();

        $this->assertSame($verifiedAt, $target->refresh()->email_verified_at->toISOString());
        Notification::assertNothingSent();
    }

    public function test_duplicate_email_is_rejected_without_mutating_target(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $target = User::factory()->create(['name' => 'Original', 'email' => 'original@example.com']);

        $this->actingAs($admin)->patch("/admin/users/{$target->id}/profile", [
            'name' => 'Changed', 'email' => $other->email,
        ])->assertSessionHasErrors('email');

        $this->assertSame('Original', $target->refresh()->name);
        $this->assertSame('original@example.com', $target->email);
        $this->assertNotNull($target->email_verified_at);
        Notification::assertNothingSent();
        $this->assertDatabaseMissing('admin_audit_logs', ['actor_user_id' => $admin->id, 'action' => 'update_profile']);
    }

    public function test_profile_editor_can_edit_self_but_not_accounts_above_their_permission_ceiling(): void
    {
        $editor = User::factory()->create();
        $role = PlatformRole::query()->create(['code' => 'profile_editor', 'name' => 'Profile editor']);
        foreach (['admin.access', 'admin.users.view', 'admin.users.manage_profile'] as $permission) {
            $role->permissionGrants()->create(['permission' => $permission]);
        }
        $editor->platformRoles()->attach($role);
        $support = User::factory()->support()->create();
        $superAdmin = User::factory()->admin()->create();

        $this->actingAs($editor)->patch("/admin/users/{$editor->id}/profile", [
            'name' => 'Self Edited', 'email' => $editor->email,
        ])->assertRedirect();
        $this->assertSame('Self Edited', $editor->refresh()->name);

        foreach ([$support, $superAdmin] as $target) {
            $original = $target->name;
            $this->actingAs($editor)->patch("/admin/users/{$target->id}/profile", [
                'name' => 'Forbidden', 'email' => $target->email,
            ])->assertForbidden();
            $this->assertSame($original, $target->refresh()->name);
        }
    }

    public function test_profile_editor_cannot_edit_inactive_accounts_above_their_ceiling(): void
    {
        $actor = User::factory()->create();
        $editorRole = PlatformRole::query()->create(['name' => 'Profile editor', 'code' => 'profile_editor']);
        $editorRole->permissionGrants()->createMany([
            ['permission' => 'admin.access'],
            ['permission' => 'admin.users.view'],
            ['permission' => 'admin.users.manage_profile'],
        ]);
        $actor->platformRoles()->attach($editorRole);
        $strongRole = PlatformRole::query()->create(['name' => 'Billing ops', 'code' => 'billing_ops']);
        $strongRole->permissionGrants()->createMany([
            ['permission' => 'admin.access'],
            ['permission' => 'admin.billing.manage'],
        ]);
        $suspended = User::factory()->create(['is_suspended' => true]);
        $suspended->platformRoles()->attach($strongRole);
        $pending = User::factory()->create(['deletion_requested_at' => now()]);
        $pending->platformRoles()->attach($strongRole);

        foreach ([$suspended, $pending] as $target) {
            $this->actingAs($actor)->patch("/admin/users/{$target->id}/profile", [
                'name' => 'Takeover', 'email' => 'takeover@example.com',
            ])->assertForbidden();
            $this->assertNotSame('takeover@example.com', $target->fresh()->email);
        }
    }

    public function test_only_builtin_super_admin_can_edit_another_super_admin_even_with_all_permissions(): void
    {
        $editor = User::factory()->create();
        $role = PlatformRole::query()->create(['code' => 'all_permissions', 'name' => 'All permissions']);
        foreach (\App\Enums\PlatformPermission::cases() as $permission) {
            $role->permissionGrants()->create(['permission' => $permission->value]);
        }
        $editor->platformRoles()->attach($role);
        $target = User::factory()->admin()->create();

        $this->actingAs($editor)->patch("/admin/users/{$target->id}/profile", [
            'name' => 'Forbidden', 'email' => $target->email,
        ])->assertForbidden();
    }

    public function test_notification_failure_reports_saved_profile_and_resend_guidance(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Mail unavailable');
        });

        $response = $this->actingAs($admin)->patch("/admin/users/{$target->id}/profile", [
            'name' => 'Saved Name', 'email' => 'saved@example.com',
        ])->assertRedirect()->assertSessionHasErrors('profile');

        $this->assertStringContainsString('resend', strtolower(session('errors')->first('profile')));
        $this->assertSame('Saved Name', $target->refresh()->name);
        $this->assertSame('saved@example.com', $target->email);
        $this->assertNull($target->email_verified_at);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_user_id' => $admin->id, 'action' => 'update_profile']);
    }
}
