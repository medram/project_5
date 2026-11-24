<?php

namespace Tests\Feature\Admin;

use App\Models\CustomDomain;
use App\Models\User;
use App\Services\Domains\DnsTxtLookup;
use App\Services\Domains\HttpsDomainChallengeProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DomainAdminEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_renames_domain_and_resets_verification(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $domain = $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id, 'hostname' => 'old.example.test',
            'verification_token' => 'old-token', 'status' => 'active',
            'verified_at' => now(), 'last_checked_at' => now(), 'failure_reason' => 'old failure',
        ]);

        $this->actingAs($admin)->patch("/admin/custom-domains/{$domain->id}", [
            'hostname' => 'NEW.Example.Test.', 'confirmation' => 'old.example.test',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/custom-domains');

        $domain->refresh();
        $this->assertSame('new.example.test', $domain->hostname);
        $this->assertNotSame('old-token', $domain->verification_token);
        $this->assertSame('pending_dns', $domain->status);
        $this->assertNull($domain->verified_at);
        $this->assertNull($domain->last_checked_at);
        $this->assertNull($domain->failure_reason);
        $this->assertSame($owner->current_workspace_id, $domain->workspace_id);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id, 'permission' => 'admin.content.domains.manage', 'subject_id' => $domain->id,
        ]);
    }

    public function test_rename_requires_current_hostname_and_unique_safe_new_hostname(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $domain = $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id, 'hostname' => 'old.example.test', 'verification_token' => 'token', 'status' => 'active',
        ]);
        $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id, 'hostname' => 'taken.example.test', 'verification_token' => 'another', 'status' => 'pending_dns',
        ]);

        $this->actingAs($admin)->patch("/admin/custom-domains/{$domain->id}", [
            'hostname' => 'taken.example.test', 'confirmation' => 'wrong.example.test',
        ])->assertSessionHasErrors(['hostname', 'confirmation']);
        $this->assertSame('old.example.test', $domain->fresh()->hostname);
        $this->assertSame('token', $domain->fresh()->verification_token);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_admin_reissue_resets_verification_and_returns_to_editor(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $domain = $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id, 'hostname' => 'old.example.test', 'verification_token' => 'old-token',
            'status' => 'active', 'verified_at' => now(),
        ]);

        $this->actingAs($admin)->post("/admin/custom-domains/{$domain->id}/reissue")
            ->assertRedirect("/admin/custom-domains/{$domain->id}");
        $this->assertNotSame('old-token', $domain->fresh()->verification_token);
        $this->assertSame('pending_dns', $domain->fresh()->status);
        $this->assertNull($domain->fresh()->verified_at);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id, 'permission' => 'admin.content.domains.manage', 'subject_id' => $domain->id,
        ]);
    }

    public function test_verification_checks_outside_mutation_and_discards_stale_results(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $domain = $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id, 'hostname' => 'old.example.test', 'verification_token' => 'old-token', 'status' => 'pending_dns',
        ]);
        $level = DB::transactionLevel();
        $dns = new class($domain) extends DnsTxtLookup {
            public int $level = -1;
            public function __construct(private CustomDomain $domain) {}
            public function recordsFor(string $hostname): array
            {
                $this->level = DB::transactionLevel();
                $this->domain->update(['verification_token' => 'new-token']);
                return ['linkpilot=old-token'];
            }
        };
        $https = new class extends HttpsDomainChallengeProbe {
            public function respondsWithToken(string $hostname, string $token): bool { return true; }
        };
        $this->app->instance(DnsTxtLookup::class, $dns);
        $this->app->instance(HttpsDomainChallengeProbe::class, $https);

        $this->actingAs($admin)->post("/admin/custom-domains/{$domain->id}/verify")
            ->assertRedirect("/admin/custom-domains/{$domain->id}");
        $this->assertSame($level, $dns->level);
        $this->assertSame('new-token', $domain->fresh()->verification_token);
        $this->assertSame('pending_dns', $domain->fresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id, 'permission' => 'admin.content.domains.manage', 'subject_id' => $domain->id,
        ]);
    }
}
