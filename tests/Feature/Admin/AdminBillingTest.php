<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Models\WorkspacePlanOverride;
use App\Services\Billing\StripeReadiness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_and_subscriptions_require_billing_permissions(): void
    {
        $ordinary = User::factory()->create();
        $support = User::factory()->support()->create();
        $administrator = User::factory()->admin()->create();

        $this->actingAs($ordinary)->get('/admin/plans')->assertForbidden();
        $this->actingAs($support)->get('/admin/plans')->assertForbidden();
        $this->actingAs($support)->get('/admin/subscriptions')->assertForbidden();

        $this->withoutVite();
        $this->actingAs($administrator)
            ->get('/admin/plans')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/plans/index')
                ->has('plans', 3)
                ->has('readiness'));

        $this->actingAs($administrator)
            ->get('/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/subscriptions/index')
                ->has('workspaces.data')
                ->has('plans')
                ->has('sources')
                ->has('readiness'));
    }

    public function test_plan_edits_change_only_display_metadata_and_price_mappings(): void
    {
        $administrator = User::factory()->admin()->create();
        $plan = Plan::query()->where('code', 'free')->sole();
        $originalLimits = [
            'monthly_price' => $plan->monthly_price,
            'annual_price' => $plan->annual_price,
            'link_limit' => $plan->link_limit,
        ];

        $this->actingAs($administrator)
            ->patch('/admin/plans/free', [
                'name' => 'Starter',
                'description' => 'A revised description.',
                'sort_order' => 4,
                'featured' => true,
                'is_active' => true,
                'stripe_monthly_price_id' => 'price_free_monthly',
                'stripe_yearly_price_id' => 'price_free_yearly',
                'code' => 'hacked',
                'monthly_price' => 999999,
                'link_limit' => 999999,
            ])
            ->assertRedirect();

        $plan->refresh();
        $this->assertSame('Starter', $plan->name);
        $this->assertSame('A revised description.', $plan->display_metadata['description']);
        $this->assertTrue($plan->display_metadata['featured']);
        $this->assertSame(4, $plan->display_metadata['sort_order']);
        $this->assertSame('price_free_monthly', $plan->stripe_monthly_price_id);
        $this->assertSame('price_free_yearly', $plan->stripe_yearly_price_id);
        $this->assertSame('free', $plan->code);
        foreach ($originalLimits as $column => $value) {
            $this->assertSame($value, $plan->getAttribute($column));
        }

        $this->assertDatabaseHas('admin_audit_logs', [
            'permission' => 'admin.billing.manage',
            'action' => 'update_plan',
            'subject_id' => $plan->id,
        ]);
    }

    public function test_override_creation_requires_reason_and_active_plan_and_is_idempotent(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();

        $this->actingAs($administrator)
            ->post("/admin/subscriptions/{$workspace->id}/overrides", [
                'plan' => 'pro',
                'source' => 'support_grant',
                'reason' => 'Temporary support grant.',
                'expires_at' => CarbonImmutable::now('UTC')->addDays(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('workspace_plan_overrides', 1);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'assign_override',
            'subject_id' => $workspace->id,
        ]);

        $this->actingAs($administrator)
            ->post("/admin/subscriptions/{$workspace->id}/overrides", [
                'plan' => 'pro',
                'source' => 'support_grant',
                'reason' => 'Temporary support grant.',
                'expires_at' => CarbonImmutable::now('UTC')->addDays(3)->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('workspace_plan_overrides', 1);
        $this->assertDatabaseCount('admin_audit_logs', 1);

        $this->actingAs($administrator)
            ->post("/admin/subscriptions/{$workspace->id}/overrides", [
                'plan' => 'not-a-plan',
                'source' => 'support_grant',
                'reason' => 'Invalid plan.',
            ])
            ->assertSessionHasErrors('plan');
    }

    public function test_override_expiry_falls_through_to_cashier_then_free_and_can_be_revoked(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();

        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));
        $this->actingAs($administrator)
            ->post("/admin/subscriptions/{$workspace->id}/overrides", [
                'plan' => 'pro',
                'source' => 'support_grant',
                'reason' => 'Expiring support grant.',
                'expires_at' => '2026-11-14',
            ])
            ->assertRedirect();

        $this->withoutVite();
        $this->get('/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspaces.data.0.effectivePlan.code', 'pro')
                ->where('workspaces.data.0.effectivePlan.source', 'support_grant'));

        $this->travel(3)->days();
        $this->get('/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspaces.data.0.effectivePlan.code', 'free')
                ->where('workspaces.data.0.effectivePlan.source', 'free')
                ->where('workspaces.data.0.overrides', []));

        $override = WorkspacePlanOverride::query()->sole();
        $this->actingAs($administrator)
            ->patch("/admin/subscriptions/{$workspace->id}/overrides/{$override->id}/revoke", [
                'reason' => 'Cleanup after expiry.',
            ])
            ->assertRedirect();

        $this->assertNotNull($override->refresh()->revoked_at);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'revoke_override',
            'subject_id' => $workspace->id,
        ]);
    }

    public function test_subscription_index_shows_metadata_without_payment_data_or_secrets(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspace->id,
            'type' => 'default',
            'stripe_id' => 'sub_hidden_payment_identifier',
            'stripe_status' => 'active',
            'stripe_price' => 'price_hidden_mapping',
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withoutVite();
        $response = $this->actingAs($administrator)
            ->get('/admin/subscriptions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspaces.data.0.provider.status', 'active')
                ->where('workspaces.data.0.provider.configured', false)
                ->missing('workspaces.data.0.customer')
                ->missing('workspaces.data.0.stripe_id'));

        $response->assertDontSee('sub_hidden_payment_identifier');
        $response->assertDontSee('sk_test_');
    }

    public function test_provider_actions_are_disabled_without_stripe_configuration(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspace->id,
            'type' => 'default',
            'stripe_id' => 'sub_missing_provider',
            'stripe_status' => 'active',
            'stripe_price' => 'price_missing_provider',
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(app(StripeReadiness::class)->configured());

        $this->actingAs($administrator)
            ->post("/admin/subscriptions/{$workspace->id}/cancel")
            ->assertRedirect()
            ->assertSessionHasErrors('provider');
        $this->actingAs($administrator)
            ->post("/admin/subscriptions/{$workspace->id}/resume")
            ->assertRedirect()
            ->assertSessionHasErrors('provider');

        $this->assertDatabaseHas('subscriptions', [
            'workspace_id' => $workspace->id,
            'stripe_status' => 'active',
            'ends_at' => null,
        ]);
    }

    public function test_override_revoke_is_scoped_to_the_workspace(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $other = User::factory()->create()->personalWorkspace()->firstOrFail();
        $plan = Plan::query()->where('code', 'pro')->sole();
        $override = WorkspacePlanOverride::query()->create([
            'workspace_id' => $other->id,
            'plan_id' => $plan->id,
            'actor_user_id' => $administrator->id,
            'source' => 'support_grant',
            'reason' => 'Scoped override.',
            'starts_at' => now(),
        ]);

        $this->actingAs($administrator)
            ->patch("/admin/subscriptions/{$workspace->id}/overrides/{$override->id}/revoke", [
                'reason' => 'Wrong workspace.',
            ])
            ->assertNotFound();

        $this->assertNull($override->refresh()->revoked_at);
    }
}
