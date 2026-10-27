<?php

namespace Tests\Feature\Links;

use App\Models\AnalyticsEvent;
use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_owner_sees_only_the_selected_links_utc_window(): void
    {
        config()->set('app.timezone', 'America/Los_Angeles');
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', 'UTC'));

        $link = Link::factory()->create();
        $otherLink = Link::factory()->for($link->user)->create();
        $windowStart = Carbon::parse('2026-10-13 00:00:00', 'UTC');

        Click::factory()->for($link)->create(['occurred_at' => $windowStart]);
        Click::factory()->count(2)->for($link)->create([
            'occurred_at' => Carbon::parse('2026-10-18 09:00:00', 'UTC'),
        ]);
        Click::factory()->for($link)->create([
            'occurred_at' => $windowStart->copy()->subSecond(),
        ]);
        Click::factory()->for($link)->create([
            'occurred_at' => Carbon::parse('2026-10-20 00:00:00', 'UTC'),
        ]);
        Click::factory()->for($otherLink)->create([
            'occurred_at' => Carbon::parse('2026-10-19 10:00:00', 'UTC'),
        ]);

        $this->eventFor($link, $windowStart, 'overlay.view', 'a');
        $this->eventFor(
            $link,
            Carbon::parse('2026-10-18 09:00:00', 'UTC'),
            'link.clicked',
            'b',
        );
        $this->eventFor($link, $windowStart->copy()->subSecond(), 'link.clicked', 'c');
        $this->eventFor(
            $link,
            Carbon::parse('2026-10-20 00:00:00', 'UTC'),
            'link.clicked',
            'd',
        );
        $this->eventFor(
            $otherLink,
            Carbon::parse('2026-10-19 10:00:00', 'UTC'),
            'link.clicked',
            'e',
        );

        $this->withoutVite();

        $this->actingAs($link->user)
            ->get("/dashboard/links/{$link->id}/insights")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/insights', false)
                ->where('link.id', $link->id)
                ->where('analytics.days', 30)
                ->where('analytics.totalClicks', 4)
                ->where('analytics.totalEvents', 3));

        $this->actingAs($link->user)
            ->get("/dashboard/links/{$link->id}/insights?days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/insights', false)
                ->where('link.id', $link->id)
                ->where('analytics.days', 7)
                ->where('analytics.totalClicks', 3)
                ->where('analytics.uniqueVisitors', 2)
                ->where('analytics.totalEvents', 2)
                ->has('analytics.daily', 7)
                ->where('analytics.daily.0', [
                    'date' => '2026-10-13',
                    'clicks' => 1,
                    'events' => 1,
                ])
                ->where('analytics.daily.5', [
                    'date' => '2026-10-18',
                    'clicks' => 2,
                    'events' => 1,
                ])
                ->where('analytics.daily.6', [
                    'date' => '2026-10-19',
                    'clicks' => 0,
                    'events' => 0,
                ])
                ->where('analytics.eventsByName', [
                    ['name' => 'link.clicked', 'count' => 1],
                    ['name' => 'overlay.view', 'count' => 1],
                ]));

        $this->actingAs($link->user)
            ->get("/dashboard/links/{$link->id}/insights?days=14")
            ->assertUnprocessable();
    }

    public function test_member_cannot_view_link_insights_outside_the_active_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = \App\Models\Workspace::query()->create([
            'name' => 'Another workspace',
            'slug' => 'another-insights-'.$user->id,
            'owner_id' => $user->id,
        ]);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $link = Link::factory()->for($user)->create([
            'slug' => 'inactive-workspace-link',
            'workspace_id' => $workspace->id,
        ]);

        $this->withoutVite();

        $this->actingAs($user)
            ->get("/dashboard/links/{$link->id}/insights")
            ->assertNotFound();
    }

    public function test_other_user_cannot_view_link_insights(): void
    {
        $link = Link::factory()->create();

        $this
            ->actingAs(User::factory()->create())
            ->get("/dashboard/links/{$link->id}/insights")
            ->assertForbidden();
    }
    private function eventFor(
        Link $link,
        \DateTimeInterface $occurredAt,
        string $eventName,
        string $visitor,
    ): AnalyticsEvent {
        return AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $link->workspace_id,
            'user_id' => $link->user_id,
            'link_id' => $link->id,
            'event_name' => $eventName,
            'occurred_at' => $occurredAt,
            'visitor_hash' => str_repeat($visitor, 64),
            'visitor_key_version' => 'test-v1',
            'referrer_host' => 'referrer.example',
            'browser' => 'Chrome',
            'platform' => 'Linux',
        ]);
    }

}
