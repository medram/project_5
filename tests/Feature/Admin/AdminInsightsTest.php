<?php

namespace Tests\Feature\Admin;

use App\Models\AnalyticsEvent;
use App\Models\Click;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspacePlanOverride;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_require_the_explicit_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/insights')
            ->assertForbidden();

        $this->withoutVite();
        $this->actingAs(User::factory()->support()->create())
            ->get('/admin/insights')
            ->assertOk();

        $this->get('/admin/insights/visitors')->assertOk();
    }

    public function test_presets_and_custom_ranges_are_validated_and_zero_filled(): void
    {
        $administrator = User::factory()->admin()->create();
        $this->withoutVite();

        $this->actingAs($administrator)
            ->get('/admin/insights?range=7')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/insights/index')
                ->where('insights.range.days', 7)
                ->has('insights.daily', 7)
                ->where('insights.daily.0.date', CarbonImmutable::now('UTC')->subDays(6)->toDateString()));

        $this->actingAs($administrator)
            ->get('/admin/insights?range=90')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('insights.daily', 90));

        $this->actingAs($administrator)
            ->get('/admin/insights?range=custom&from=2026-11-01&to=2026-11-03')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('insights.range.days', 3)
                ->has('insights.daily', 3));

        $this->actingAs($administrator)
            ->get('/admin/insights?range=custom&from=2025-01-01&to=2026-01-02')
            ->assertRedirect()
            ->assertSessionHasErrors('to');

        $this->actingAs($administrator)
            ->get('/admin/insights?range=custom&from=2026-11-03&to=2026-11-01')
            ->assertRedirect()
            ->assertSessionHasErrors('to');
    }

    public function test_platform_totals_mrr_resources_and_provider_states_are_truthful(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $pro = Plan::query()->where('code', 'pro')->sole();
        $pro->update([
            'stripe_monthly_price_id' => 'price_pro_monthly_test',
            'stripe_yearly_price_id' => 'price_pro_yearly_test',
        ]);
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspace->id,
            'type' => 'default',
            'stripe_id' => 'sub_provider_test',
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro_monthly_test',
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $overrideWorkspace = User::factory()->create()->personalWorkspace()->firstOrFail();
        WorkspacePlanOverride::query()->create([
            'workspace_id' => $overrideWorkspace->id,
            'plan_id' => $pro->id,
            'actor_user_id' => $administrator->id,
            'source' => 'admin_override',
            'reason' => 'Must never count as revenue.',
            'starts_at' => now()->subDay(),
        ]);

        $this->withoutVite();
        $this->actingAs($administrator)
            ->get('/admin/insights?range=30')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('insights.subscriptions.mrrCents', 2900)
                ->where('insights.subscriptions.arrCents', 34800)
                ->where('insights.subscriptions.mappedSubscriptions', 1)
                ->where('insights.subscriptions.unmappedSubscriptions', 0)
                ->where('insights.subscriptions.states', fn ($states) => collect($states)->contains(
                    fn ($state) => $state['state'] === 'active' && $state['count'] === 1,
                ))
                ->where('insights.accounts.total', 2)
                ->where('insights.workspaces.total', 2)
                ->where('insights.resources.members', 2)
                ->has('insights.resources.links')
                ->has('insights.dimensions.countries')
                ->where('insights.kAnonymityThreshold', 5));
    }

    public function test_analytics_totals_are_aggregate_and_dimensions_collapse_low_cardinality_buckets(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $link = Link::factory()->for($administrator)->create([
            'workspace_id' => $workspace->id,
        ]);
        $hashes = [];

        foreach (range(1, 6) as $index) {
            $hashes[] = hash('sha256', "us-{$index}");
            $this->event($administrator, $workspace, $link, 'link.clicked', 'US', 'Chrome', 'Windows', $hashes[array_key_last($hashes)]);
        }

        foreach (range(1, 2) as $index) {
            $hashes[] = hash('sha256', "ca-{$index}");
            $this->event($administrator, $workspace, $link, 'link.clicked', 'CA', 'Safari', 'iOS', $hashes[array_key_last($hashes)]);
        }

        $link->clicks()->create(['occurred_at' => now('UTC')->subDay()]);

        $this->withoutVite();
        $response = $this->actingAs($administrator)
            ->get('/admin/insights/visitors?range=7')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/insights/visitors')
                ->where('insights.totals.events', 8)
                ->where('insights.totals.uniqueVisitors', 8)
                ->where('insights.totals.clicks', 1)
                ->where('insights.dimensions.countries', fn ($countries) => collect($countries)->contains(
                    fn ($country) => $country['name'] === 'US' && $country['count'] === 6,
                ) && collect($countries)->contains(
                    fn ($country) => $country['name'] === 'Other' && $country['count'] === 2,
                ) && ! collect($countries)->contains(fn ($country) => $country['name'] === 'CA'))
                ->has('insights.eventSeries')
                ->has('insights.daily', 7));

        foreach ($hashes as $hash) {
            $response->assertDontSee($hash);
        }

        $response->assertDontSee('visitor_hash');
    }

    public function test_daily_series_zero_fills_and_counts_only_the_selected_window(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $link = Link::factory()->for($administrator)->create(['workspace_id' => $workspace->id]);
        $this->event($administrator, $workspace, $link, 'link.clicked', 'US', 'Chrome', 'Windows', hash('sha256', 'today'));
        $this->event($administrator, $workspace, $link, 'link.clicked', 'US', 'Chrome', 'Windows', hash('sha256', 'old'), CarbonImmutable::now('UTC')->subDays(40));

        $this->withoutVite();
        $this->actingAs($administrator)
            ->get('/admin/insights?range=7')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('insights.engagement.events', 1)
                ->where('insights.daily.6.events', 1)
                ->where('insights.daily.0.events', 0));
    }

    private function event(
        User $user,
        Workspace $workspace,
        Link $link,
        string $name,
        string $country,
        string $browser,
        string $platform,
        string $hash,
        ?CarbonInterface $occurredAt = null,
    ): AnalyticsEvent {
        return AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'link_id' => $link->id,
            'event_name' => $name,
            'occurred_at' => $occurredAt ?? now('UTC'),
            'visitor_hash' => $hash,
            'visitor_key_version' => 'v1',
            'referrer_host' => 'referrer.example',
            'browser' => $browser,
            'platform' => $platform,
            'country_code' => $country,
        ]);
    }
}