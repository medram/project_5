<?php

namespace Tests\Feature\BioPages;

use App\Models\AnalyticsEvent;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BioPageInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_owner_sees_only_selected_page_consent_analytics_in_utc_window(): void
    {
        config()->set('app.timezone', 'America/Los_Angeles');
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', 'UTC'));

        $owner = User::factory()->create();
        $selectedPage = BioPage::factory()->for($owner)->create();
        $otherPage = BioPage::factory()->for($owner)->create();
        $foreignPage = BioPage::factory()->create();
        $selectedLink = BioLink::factory()->for($selectedPage)->create();
        $otherLink = BioLink::factory()->for($otherPage)->create();
        $windowStart = Carbon::parse('2026-10-13 00:00:00', 'UTC');

        $this->eventFor($selectedPage, $windowStart, 'bio_page.view');
        $this->eventFor(
            $selectedPage,
            Carbon::parse('2026-10-18 09:00:00', 'UTC'),
            'bio_page.clicked',
            $selectedLink,
        );
        $this->eventFor(
            $selectedPage,
            Carbon::parse('2026-10-18 10:00:00', 'UTC'),
            'bio_page.clicked',
            $selectedLink,
        );
        $this->eventFor($selectedPage, $windowStart->copy()->subSecond(), 'bio_page.view');
        $this->eventFor(
            $selectedPage,
            Carbon::parse('2026-10-20 00:00:00', 'UTC'),
            'bio_page.clicked',
            $selectedLink,
        );
        $this->eventFor(
            $selectedPage,
            Carbon::parse('2026-10-18 11:00:00', 'UTC'),
            'overlay.view',
        );
        $this->eventFor(
            $otherPage,
            Carbon::parse('2026-10-18 12:00:00', 'UTC'),
            'bio_page.view',
        );
        $this->eventFor(
            $otherPage,
            Carbon::parse('2026-10-18 13:00:00', 'UTC'),
            'bio_page.clicked',
            $otherLink,
        );

        $this->withoutVite();

        $this->actingAs($owner)
            ->get("/dashboard/biolinks/{$selectedPage->id}/insights?days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('bio-pages/edit', false)
                ->where('bioPage.id', $selectedPage->id)
                ->where('insights', [
                    'days' => 7,
                    'startDate' => '2026-10-13',
                    'endDate' => '2026-10-19',
                    'totalViews' => 1,
                    'totalClicks' => 2,
                    'daily' => [
                        ['date' => '2026-10-13', 'views' => 1, 'clicks' => 0],
                        ['date' => '2026-10-14', 'views' => 0, 'clicks' => 0],
                        ['date' => '2026-10-15', 'views' => 0, 'clicks' => 0],
                        ['date' => '2026-10-16', 'views' => 0, 'clicks' => 0],
                        ['date' => '2026-10-17', 'views' => 0, 'clicks' => 0],
                        ['date' => '2026-10-18', 'views' => 0, 'clicks' => 2],
                        ['date' => '2026-10-19', 'views' => 0, 'clicks' => 0],
                    ],
                ]));

        $this->actingAs($owner)
            ->get("/dashboard/biolinks/{$foreignPage->id}/insights?days=7")
            ->assertNotFound();
    }

    private function eventFor(
        BioPage $page,
        \DateTimeInterface $occurredAt,
        string $eventName,
        ?BioLink $bioLink = null,
    ): AnalyticsEvent {
        return AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $page->workspace_id,
            'user_id' => $page->user_id,
            'bio_page_id' => $page->id,
            'bio_link_id' => $bioLink?->id,
            'event_name' => $eventName,
            'occurred_at' => $occurredAt,
            'visitor_hash' => hash('sha256', Str::random()),
            'visitor_key_version' => 'test-v1',
        ]);
    }
}
