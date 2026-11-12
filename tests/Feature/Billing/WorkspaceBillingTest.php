<?php

namespace Tests\Feature\Billing;

use App\Enums\PlanMetric;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_a_free_personal_workspace_subscription_and_see_its_usage(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $subscription = app(WorkspaceUsage::class)->subscriptionFor($workspace)->load('plan');

        $this->assertSame('active', $subscription->status);
        $this->assertSame('manual', $subscription->source);
        $this->assertSame('free', $subscription->plan->code);

        $this->withoutVite();
        $this->actingAs($user)
            ->get('/account-settings/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/index', false)
                ->where('subscription.plan.code', 'free')
                ->where('usage.links.used', 0)
                ->where('usage.links.limit', 25)
                ->where('canManageBilling', true)
                ->has('plans', 3),
            );
    }

    public function test_free_workspace_cannot_create_a_twenty_sixth_link(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        Link::factory()->count(25)->for($user)->create();

        $this->actingAs($user)
            ->post('/dashboard/links', $this->linkAttributes('free-limit-link'))
            ->assertSessionHasErrors('plan');

        $this->assertSame(25, $workspace->links()->count());
    }

    public function test_plan_enforcement_does_not_invoke_the_creator_after_the_workspace_limit_is_reached(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        Link::factory()->count(25)->for($user)->create();
        $creatorCalls = 0;

        try {
            app(EnforcesPlanLimits::class)->create($workspace, PlanMetric::Links, function (Workspace $lockedWorkspace) use ($user, &$creatorCalls): Link {
                $creatorCalls++;

                return $lockedWorkspace->links()->create([
                    ...$this->linkAttributes('must-not-exist'),
                    'user_id' => $user->id,
                ]);
            });

            $this->fail('Expected workspace plan enforcement to reject the creation callback.');
        } catch (ValidationException) {
            $this->assertSame(0, $creatorCalls);
            $this->assertDatabaseMissing('links', ['slug' => 'must-not-exist']);
        }
    }

    public function test_pro_and_business_workspace_capacities_are_enforced(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->assignPlan($workspace, 'pro');
        Link::factory()->count(499)->for($user)->create();

        app(EnforcesPlanLimits::class)->create(
            $workspace,
            PlanMetric::Links,
            fn (Workspace $lockedWorkspace): Link => $lockedWorkspace->links()->create([
                ...$this->linkAttributes('pro-capacity-link'),
                'user_id' => $user->id,
            ]),
        );

        $this->assertSame(500, $workspace->links()->count());

        $businessUser = User::factory()->create();
        $businessWorkspace = $businessUser->personalWorkspace()->sole();
        $this->assignPlan($businessWorkspace, 'business');
        Link::factory()->count(500)->for($businessUser)->create();

        app(EnforcesPlanLimits::class)->create(
            $businessWorkspace,
            PlanMetric::Links,
            fn (Workspace $lockedWorkspace): Link => $lockedWorkspace->links()->create([
                ...$this->linkAttributes('business-capacity-link'),
                'user_id' => $businessUser->id,
            ]),
        );

        $this->assertSame(501, $businessWorkspace->links()->count());
    }

    public function test_downgrading_a_workspace_keeps_existing_links_public_but_rejects_new_links(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $this->assignPlan($workspace, 'pro');
        $existingLink = Link::factory()->for($user)->create([
            'slug' => 'downgrade-live-link',
            'destination_url' => 'https://example.com/still-live',
        ]);
        Link::factory()->count(25)->for($user)->create();
        $this->assignPlan($workspace, 'free');

        $this->get('/downgrade-live-link')->assertRedirect($existingLink->destination_url);

        $this->actingAs($user)
            ->post('/dashboard/links', $this->linkAttributes('downgrade-new-link'))
            ->assertSessionHasErrors('plan');

        $this->assertSame(26, $workspace->links()->count());
    }

    public function test_workspace_members_can_view_billing_but_only_the_owner_can_change_the_plan(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Team workspace',
            'slug' => 'team-'.$owner->id,
            'owner_id' => $owner->id,
        ]);
        $workspace->members()->attach($owner, ['role' => 'owner']);
        $workspace->members()->attach($member, ['role' => 'member']);
        $workspace->members()->attach($admin, ['role' => 'admin']);
        $this->assignPlan($workspace, 'pro');
        DB::table('users')
            ->whereIn('id', [$owner->id, $member->id, $admin->id])
            ->update(['current_workspace_id' => $workspace->id]);
        $owner->refresh();
        $member->refresh();
        $admin->refresh();
        $this->withoutVite();

        foreach ([$member, $admin] as $readOnlyUser) {
            $this->actingAs($readOnlyUser)
                ->get('/account-settings/billing')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('subscription.plan.code', 'pro')
                    ->where('canManageBilling', false),
                );

            $this->actingAs($readOnlyUser)
                ->patch('/account-settings/billing/plan', ['plan' => 'free'])
                ->assertForbidden();
        }

        $this->actingAs($owner)
            ->patch('/account-settings/billing/plan', ['plan' => 'free'])
            ->assertRedirect('/account-settings/billing');

        $this->assertSame('free', $workspace->legacySubscription()->with('plan')->firstOrFail()->plan->code);
    }

    /**
     * @return array<string, mixed>
     */
    private function linkAttributes(string $slug): array
    {
        return [
            'destination_url' => "https://example.com/{$slug}",
            'title' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'is_active' => true,
        ];
    }

    private function assignPlan(Workspace $workspace, string $planCode): void
    {
        app(WorkspaceUsage::class)->assignPlan(
            $workspace,
            Plan::query()->where('code', $planCode)->sole(),
        );
    }
}
