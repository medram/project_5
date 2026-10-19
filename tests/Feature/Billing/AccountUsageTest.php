<?php

namespace Tests\Feature\Billing;

use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Billing\EnforcesPlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_one_active_free_subscription_and_see_it_on_billing(): void
    {
        $user = User::factory()->create();

        $subscription = $user->subscription()->with('plan')->sole();

        $this->assertSame('active', $subscription->status);
        $this->assertSame('manual', $subscription->source);
        $this->assertSame('free', $subscription->plan->code);

        $this->withoutVite();

        $this
            ->actingAs($user)
            ->get('/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('billing/index', false)
                ->where('subscription.plan.code', 'free')
                ->where('usage.links.used', 0)
                ->where('usage.links.limit', 25)
                ->has('plans', 3),
            );
    }

    public function test_backfill_migration_gives_legacy_users_one_free_subscription(): void
    {
        $legacyUser = User::withoutEvents(fn () => User::factory()->create());

        $this->assertDatabaseMissing('user_subscriptions', ['user_id' => $legacyUser->id]);

        $migration = require database_path('migrations/2026_10_19_000800_backfill_free_user_subscriptions.php');
        $migration->up();

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $legacyUser->id,
            'plan_id' => Plan::query()->where('code', 'free')->sole()->id,
            'status' => 'active',
            'source' => 'manual',
        ]);
        $this->assertSame(1, UserSubscription::query()->where('user_id', $legacyUser->id)->count());
    }

    public function test_free_accounts_cannot_create_a_twenty_sixth_link(): void
    {
        $user = User::factory()->create();
        Link::factory()->count(25)->for($user)->create();

        $this
            ->actingAs($user)
            ->post('/links', $this->linkAttributes('free-limit-link'))
            ->assertSessionHasErrors('plan');

        $this->assertSame(25, $user->links()->count());
    }

    public function test_plan_enforcement_does_not_invoke_the_creator_after_limit_is_reached(): void
    {
        $user = User::factory()->create();
        Link::factory()->count(25)->for($user)->create();
        $creatorCalls = 0;

        try {
            app(EnforcesPlanLimits::class)->create($user, 'links', function (User $lockedUser) use (&$creatorCalls): Link {
                $creatorCalls++;

                return $lockedUser->links()->create($this->linkAttributes('must-not-exist'));
            });

            $this->fail('Expected link plan enforcement to reject the creation callback.');
        } catch (ValidationException) {
            $this->assertSame(0, $creatorCalls);
            $this->assertDatabaseMissing('links', ['slug' => 'must-not-exist']);
        }
    }

    public function test_pro_accounts_can_create_to_their_configured_link_capacity(): void
    {
        $user = User::factory()->create();
        $this->assignPlan($user, 'pro');
        Link::factory()->count(499)->for($user)->create();

        app(EnforcesPlanLimits::class)->create(
            $user,
            'links',
            fn (User $lockedUser): Link => $lockedUser->links()->create($this->linkAttributes('pro-capacity-link')),
        );

        $this->assertSame(500, $user->links()->count());
    }

    public function test_business_accounts_are_unlimited_beyond_the_pro_link_capacity(): void
    {
        $user = User::factory()->create();
        $this->assignPlan($user, 'business');
        Link::factory()->count(500)->for($user)->create();

        app(EnforcesPlanLimits::class)->create(
            $user,
            'links',
            fn (User $lockedUser): Link => $lockedUser->links()->create($this->linkAttributes('business-capacity-link')),
        );

        $this->assertSame(501, $user->links()->count());
    }

    public function test_downgrades_keep_existing_links_public_but_reject_future_links(): void
    {
        $user = User::factory()->create();
        $this->assignPlan($user, 'pro');
        $existingLink = Link::factory()->for($user)->create([
            'slug' => 'downgrade-live-link',
            'destination_url' => 'https://example.com/still-live',
        ]);
        Link::factory()->count(25)->for($user)->create();

        $this->assignPlan($user, 'free');

        $this->get('/downgrade-live-link')->assertRedirect($existingLink->destination_url);

        $this
            ->actingAs($user)
            ->post('/links', $this->linkAttributes('downgrade-new-link'))
            ->assertSessionHasErrors('plan');

        $this->assertSame(26, $user->links()->count());
    }

    public function test_accounts_can_only_select_the_free_plan_for_themselves(): void
    {
        $user = User::factory()->create();
        $this->assignPlan($user, 'pro');

        $this
            ->actingAs($user)
            ->patch('/billing/plan', ['plan' => 'pro'])
            ->assertSessionHasErrors('plan');

        $this
            ->actingAs($user)
            ->patch('/billing/plan', ['plan' => 'free'])
            ->assertRedirect('/billing');

        $this->assertSame('free', $user->fresh()->subscription->plan->code);
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

    private function assignPlan(User $user, string $planCode): void
    {
        $user->subscription()->update([
            'plan_id' => Plan::query()->where('code', $planCode)->sole()->id,
        ]);
    }
}
