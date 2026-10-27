<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

    public function test_administrator_sees_workspace_metadata_and_aggregate_counts_without_private_content(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
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
                ->where('users.1.id', $account->id)
                ->where('users.1.subscription.planCode', 'free')
                ->where('users.1.counts.links', 1)
                ->missing('users.1.links')
                ->missing('users.1.destinationUrl'));

        $response->assertDontSee($link->destination_url);
    }

    public function test_admin_account_search_filters_by_metadata_and_plan_without_exposing_content(): void
    {
        $administrator = User::factory()->create([
            'is_admin' => true,
            'name' => 'Platform Administrator',
            'email' => 'administrator@example.test',
        ]);
        $matching = User::factory()->create([
            'name' => 'Northwind Account',
            'email' => 'northwind@example.test',
        ]);
        User::factory()->create([
            'name' => 'Other Account',
            'email' => 'other@example.test',
        ]);
        $pro = Plan::query()->where('code', 'pro')->sole();
        $matching->personalWorkspace()->firstOrFail()
            ->subscription()->firstOrFail()
            ->update(['plan_id' => $pro->id]);
        $matching->personalWorkspace()->firstOrFail()->links()->create([
            'user_id' => $matching->id,
            'slug' => 'private-search-link',
            'destination_url' => 'https://private-search-destination.example/path',
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this->actingAs($administrator)
            ->get('/admin?q=Northwind')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/index')
                ->where('search', 'Northwind')
                ->has('users', 1)
                ->where('users.0.id', $matching->id)
                ->missing('users.0.destinationUrl'));

        $this->actingAs($administrator)
            ->get('/admin?q=pro')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('search', 'pro')
                ->has('users', 1)
                ->where('users.0.id', $matching->id));

    }

    public function test_admin_mutations_preserve_the_filtered_search_query(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $suspendedAccount = User::factory()->create([
            'name' => 'Northwind Suspension',
        ]);
        $administratorAccount = User::factory()->create([
            'name' => 'Northwind Administrator',
        ]);
        $planAccount = User::factory()->create([
            'name' => 'Northwind Plan',
        ]);
        $pro = Plan::query()->where('code', 'pro')->sole();

        $this->actingAs($administrator)
            ->patch(
                route('admin.users.suspension.update', [
                    'user' => $suspendedAccount->id,
                    'q' => 'Northwind',
                ]),
                ['suspended' => true],
            )
            ->assertRedirect(route('admin.index', ['q' => 'Northwind']));
        $this->assertTrue($suspendedAccount->refresh()->is_suspended);

        $this->actingAs($administrator)
            ->patch(
                route('admin.users.administrator.update', [
                    'user' => $administratorAccount->id,
                    'q' => 'Northwind',
                ]),
                ['is_admin' => true],
            )
            ->assertRedirect(route('admin.index', ['q' => 'Northwind']));
        $this->assertTrue($administratorAccount->refresh()->is_admin);

        $this->actingAs($administrator)
            ->patch(
                route('admin.users.plan.update', [
                    'user' => $planAccount->id,
                    'q' => 'Northwind',
                ]),
                ['plan' => $pro->code],
            )
            ->assertRedirect(route('admin.index', ['q' => 'Northwind']));
        $this->assertSame(
            $pro->id,
            $planAccount->personalWorkspace()->firstOrFail()
                ->subscription()->firstOrFail()->plan_id,
        );
    }

    public function test_suspension_clears_sessions_and_blocks_all_authenticated_surfaces_without_unpublishing_content(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $account = User::factory()->create(['remember_token' => 'remember-me']);
        $link = $account->personalWorkspace()->firstOrFail()->links()->create([
            'user_id' => $account->id,
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
            '/dashboard/links',
            '/dashboard/qr-codes',
            '/dashboard/folders',
            '/dashboard/biolinks',
            '/account-settings/billing',
            '/account-settings/general',
            '/account-settings/security',
            '/account-settings/appearance',
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

    public function test_suspension_revalidates_the_acting_administrator_inside_the_transaction(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $account = User::factory()->create();

        // Simulate a concurrent administrator change landing after this request
        // passed the administrator middleware but before its mutation transaction.
        $demoted = false;
        Event::listen(TransactionBeginning::class, function () use ($administrator, &$demoted): void {
            if ($demoted) {
                return;
            }

            $demoted = true;
            User::query()->whereKey($administrator->id)->update(['is_admin' => false]);
        });

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/suspension", ['suspended' => true])
            ->assertForbidden();

        $this->assertTrue($demoted);
        $this->assertFalse($account->refresh()->is_suspended);
        $this->assertNull($account->suspended_at);
    }

    public function test_administrator_changes_revalidate_the_acting_administrator_inside_the_transaction(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $account = User::factory()->create();

        $suspended = false;
        Event::listen(TransactionBeginning::class, function () use ($administrator, &$suspended): void {
            if ($suspended) {
                return;
            }

            $suspended = true;
            User::query()->whereKey($administrator->id)->update([
                'is_suspended' => true,
                'suspended_at' => now(),
            ]);
        });

        $this->actingAs($administrator)
            ->patch("/admin/users/{$account->id}/administrator", ['is_admin' => true])
            ->assertForbidden();

        $this->assertTrue($suspended);
        $this->assertFalse($account->refresh()->is_admin);
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

        $this->assertSame($pro->id, $account->personalWorkspace()->firstOrFail()->subscription()->firstOrFail()->plan_id);
        $this->assertSame('free', $unrelated->personalWorkspace()->firstOrFail()->subscription()->with('plan')->firstOrFail()->plan->code);

        $this->actingAs($administrator)
            ->patch("/admin/users/{$administrator->id}/plan", ['plan' => $pro->code])
            ->assertUnprocessable();

        $this->assertSame(
            'free',
            $administrator->personalWorkspace()->firstOrFail()->subscription()->with('plan')->firstOrFail()->plan->code,
        );
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
