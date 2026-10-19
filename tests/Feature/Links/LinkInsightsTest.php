<?php

namespace Tests\Feature\Links;

use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_owner_sees_all_time_total_and_zero_filled_fourteen_day_series(): void
    {
        config()->set('app.timezone', 'UTC');
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', 'UTC'));

        $link = Link::factory()->create();
        $otherLink = Link::factory()->create();

        Click::factory()->for($link)->create([
            'occurred_at' => Carbon::parse('2026-10-06 09:00:00', 'UTC'),
        ]);
        Click::factory()->count(2)->for($link)->create([
            'occurred_at' => Carbon::parse('2026-10-18 09:00:00', 'UTC'),
        ]);
        Click::factory()->for($link)->create([
            'occurred_at' => Carbon::parse('2026-10-05 23:59:59', 'UTC'),
        ]);
        Click::factory()->for($otherLink)->create([
            'occurred_at' => Carbon::parse('2026-10-19 10:00:00', 'UTC'),
        ]);

        $this->withoutVite();

        $this
            ->actingAs($link->user)
            ->get("/links/{$link->id}/insights")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/insights', false)
                ->where('link.id', $link->id)
                ->where('totalClicks', 4)
                ->has('dailyClicks', 14)
                ->where('dailyClicks.0.date', '2026-10-06')
                ->where('dailyClicks.0.clicks', 1)
                ->where('dailyClicks.1.clicks', 0)
                ->where('dailyClicks.12.date', '2026-10-18')
                ->where('dailyClicks.12.clicks', 2)
                ->where('dailyClicks.13.date', '2026-10-19')
                ->where('dailyClicks.13.clicks', 0),
            );
    }

    public function test_other_user_cannot_view_link_insights(): void
    {
        $link = Link::factory()->create();

        $this
            ->actingAs(User::factory()->create())
            ->get("/links/{$link->id}/insights")
            ->assertForbidden();
    }
}
