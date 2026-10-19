<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_administrator_sees_account_metadata_and_aggregate_counts_without_private_content(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $account = User::factory()->create();
        $link = $account->links()->create([
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
                ->where('users.1.id', $account->id)
                ->where('users.1.subscription.planCode', 'free')
                ->where('users.1.counts.links', 1)
                ->missing('users.1.links')
                ->missing('users.1.destinationUrl'));

        $response->assertDontSee($link->destination_url);
    }

    public function test_suspension_clears_sessions_and_blocks_all_authenticated_surfaces_without_unpublishing_content(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $account = User::factory()->create(['remember_token' => 'remember-me']);
        $link = $account->links()->create([
            'slug' => 'suspended-account-link',
            'destination_url' => 'https://still-public.example',
            'is_active' => true,
        ]);

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
        $this->assertNotNull($account->suspended_at);
        $this->assertNull($account->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'suspended-account-session']);

        foreach ([
            '/dashboard',
            '/links',
            '/dashboard/qr-codes',
            '/dashboard/folders',
            '/bio-pages',
            '/billing',
            '/settings',
            '/settings/profile',
            '/settings/security',
            '/settings/appearance',
        ] as $uri) {
            $this->actingAs($account)->get($uri)->assertForbidden();
        }

        $this->get("/{$link->slug}")
            ->assertRedirect($link->destination_url);
    }

    public function test_administrators_cannot_suspend_or_demote_themselves(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);

        $this->actingAs($administrator)
            ->patch("/admin/users/{$administrator->id}/suspension", ['suspended' => true])
            ->assertUnprocessable();

        $this->actingAs($administrator)
            ->patch("/admin/users/{$administrator->id}/administrator", ['is_admin' => false])
            ->assertUnprocessable();

        $administrator->refresh();

        $this->assertTrue($administrator->is_admin);
        $this->assertFalse($administrator->is_suspended);
    }

    public function test_administrator_can_assign_an_active_plan_only_to_another_account(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $account = User::factory()->create();
        $unrelated = User::factory()->create();
        $pro = Plan::query()->where('code', 'pro')->sole();

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/plan", ['plan' => $pro->code])
            ->assertRedirect('/admin');

        $this->assertSame($pro->id, $account->refresh()->subscription->plan_id);
        $this->assertSame('free', $unrelated->refresh()->subscription->plan->code);

        $this->actingAs($administrator)
            ->patch("/admin/users/{$administrator->id}/plan", ['plan' => $pro->code])
            ->assertUnprocessable();

        $this->assertSame('free', $administrator->refresh()->subscription->plan->code);
    }

    public function test_grant_administrator_command_requires_an_explicit_existing_account(): void
    {
        $user = User::factory()->create();

        $this->artisan('linkpilot:grant-admin', ['email' => $user->email])
            ->expectsOutputToContain($user->email)
            ->assertExitCode(0);

        $this->assertTrue($user->refresh()->is_admin);
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
