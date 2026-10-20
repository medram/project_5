<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\User;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_link_delivery_preserves_clicks_without_rich_analytics(): void
    {
        $link = Link::factory()->create([
            'slug' => 'direct-analytics',
            'destination_url' => 'https://example.com/direct',
        ]);

        $this->get('/direct-analytics')
            ->assertRedirect('https://example.com/direct');

        $this->assertDatabaseHas('clicks', ['link_id' => $link->id]);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_consented_bio_views_and_clicks_store_only_minimal_exact_references(): void
    {
        config()->set('services.linkpilot.analytics.hash_key', 'test-analytics-key');
        config()->set('services.linkpilot.analytics.hash_key_version', 'test-v1');

        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create([
            'handle' => 'consented-creator',
            'is_published' => true,
        ]);
        $bioLink = BioLink::factory()->for($page)->create([
            'destination_url' => 'https://example.com/creator-link',
        ]);
        $headers = [
            'Referer' => 'https://referrer.example/path?campaign=secret',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
        ];

        $this->withoutVite();

        $this->withHeaders($headers)
            ->withCookie('linkpilot_tracking_consent', 'granted')
            ->get('/bio/consented-creator')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('bio-pages/public', false)
                ->where('links.0.url', "/bio/consented-creator/links/{$bioLink->id}"),
            );

        $view = AnalyticsEvent::query()->sole();

        $this->assertSame($owner->id, $view->user_id);
        $this->assertSame($page->id, $view->bio_page_id);
        $this->assertNull($view->bio_link_id);
        $this->assertSame('bio_page.view', $view->event_name);
        $this->assertSame('referrer.example', $view->referrer_host);
        $this->assertSame('Chrome', $view->browser);
        $this->assertSame('Windows', $view->platform);
        $this->assertSame('test-v1', $view->visitor_key_version);
        $this->assertSame(64, strlen($view->visitor_hash));
        $this->assertNotSame('127.0.0.1', $view->visitor_hash);
        $this->assertArrayNotHasKey('ip_address', $view->getAttributes());
        $this->assertArrayNotHasKey('user_agent', $view->getAttributes());
        $this->assertArrayNotHasKey('destination_url', $view->getAttributes());
        $this->assertArrayNotHasKey('access_code', $view->getAttributes());

        $this->withHeaders($headers)
            ->withCookie('linkpilot_tracking_consent', 'granted')
            ->get("/bio/consented-creator/links/{$bioLink->id}")
            ->assertRedirect('https://example.com/creator-link');

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $owner->id,
            'bio_page_id' => $page->id,
            'bio_link_id' => $bioLink->id,
            'event_name' => 'bio_page.clicked',
        ]);
    }

    public function test_unset_or_denied_consent_never_records_rich_bio_analytics(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create([
            'handle' => 'private-analytics',
            'is_published' => true,
        ]);
        $bioLink = BioLink::factory()->for($page)->create();

        $this->get('/bio/private-analytics')->assertOk();
        $this->get("/bio/private-analytics/links/{$bioLink->id}")
            ->assertRedirect($bioLink->destination_url);
        $this->withCookie('linkpilot_tracking_consent', 'denied')
            ->get('/bio/private-analytics')
            ->assertOk();
        $this->withCookie('linkpilot_tracking_consent', 'denied')
            ->get("/bio/private-analytics/links/{$bioLink->id}")
            ->assertRedirect($bioLink->destination_url);

        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_tracking_failures_do_not_block_public_rendering_or_redirects(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create([
            'handle' => 'resilient-creator',
            'is_published' => true,
        ]);
        $bioLink = BioLink::factory()->for($page)->create([
            'destination_url' => 'https://example.com/resilient',
        ]);
        $tracker = new class extends AnalyticsTracker
        {
            public int $calls = 0;

            public function __construct() {}

            /**
             * @param  array<string, mixed>  $references
             */
            public function record(
                Request $request,
                string $eventName,
                User $user,
                array $references = [],
            ): ?AnalyticsEvent {
                $this->calls++;

                throw new \RuntimeException('Tracking is unavailable.');
            }
        };

        $this->app->instance(AnalyticsTracker::class, $tracker);

        $this->withoutVite();

        $this->withCookie('linkpilot_tracking_consent', 'granted')
            ->get('/bio/resilient-creator')
            ->assertOk();
        $this->withCookie('linkpilot_tracking_consent', 'granted')
            ->get("/bio/resilient-creator/links/{$bioLink->id}")
            ->assertRedirect('https://example.com/resilient');

        $this->assertSame(2, $tracker->calls);
    }

    public function test_account_and_link_analytics_return_zero_filled_utc_windows(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create();
        $today = now('UTC')->startOfDay();

        $this->eventFor($owner, $today->copy()->subDays(6)->addHour());
        $this->eventFor($owner, $today->copy()->subDays(7)->addHour());
        $this->eventFor($owner, $today->copy()->addHour(), ['link_id' => $link->id]);

        $this->withoutVite();

        $this->actingAs($owner)
            ->get('/analytics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/index')
                ->where('windows.7.total', 2)
                ->has('windows.7.daily', 7)
                ->where('windows.30.total', 3)
                ->has('windows.30.daily', 30)
                ->where('windows.90.total', 3)
                ->has('windows.90.daily', 90),
            );

        $this->actingAs($owner)
            ->get("/links/{$link->id}/insights")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analyticsWindows.7.total', 1)
                ->has('analyticsWindows.7.daily', 7)
                ->where('analyticsWindows.30.total', 1)
                ->has('analyticsWindows.30.daily', 30)
                ->where('analyticsWindows.90.total', 1)
                ->has('analyticsWindows.90.daily', 90),
            );
    }

    public function test_access_code_unlocks_expire_and_new_versions_invalidate_prior_unlocks(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create([
            'slug' => 'versioned-unlock',
            'access_code' => Hash::make('first-code'),
            'access_code_version' => 1,
        ]);

        $this->withoutVite();

        $this->post('/versioned-unlock/unlock', ['access_code' => 'first-code'])
            ->assertRedirect('/versioned-unlock');
        $this->get('/versioned-unlock')->assertRedirect($link->destination_url);

        $this->actingAs($owner)
            ->patch("/links/{$link->id}", $this->linkPayload($link, [
                'access_code' => 'second-code',
            ]))
            ->assertRedirect('/links');

        $this->assertSame(2, $link->refresh()->access_code_version);

        $this->get('/versioned-unlock')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/unlock', false),
            );

        $this->post('/versioned-unlock/unlock', ['access_code' => 'second-code'])
            ->assertRedirect('/versioned-unlock');

        $this->travel(8)->hours();

        $this->get('/versioned-unlock')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/unlock', false),
            );

        $this->travelBack();

        $this->actingAs($owner)
            ->patch("/links/{$link->id}", $this->linkPayload($link->refresh(), [
                'clear_access_code' => true,
            ]))
            ->assertRedirect('/links');

        $this->assertSame(3, $link->refresh()->access_code_version);
    }

    public function test_access_code_failures_are_limited_per_link_and_visitor(): void
    {
        $link = Link::factory()->create([
            'slug' => 'throttled-unlock',
            'access_code' => Hash::make('correct-code'),
            'access_code_version' => 1,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/throttled-unlock/unlock', ['access_code' => 'wrong-code'])
                ->assertSessionHasErrors('access_code');
        }

        $this->post('/throttled-unlock/unlock', ['access_code' => 'wrong-code'])
            ->assertTooManyRequests();

    }

    public function test_tracking_consent_is_server_written_for_a_safe_relative_return_path(): void
    {
        $this->post('/tracking-consent', [
            'consent' => 'granted',
            'return_path' => '/bio/privacy-creator',
        ])
            ->assertRedirect('/bio/privacy-creator')
            ->assertCookie(TrackingConsent::COOKIE_NAME, 'granted');

        $this->post('/tracking-consent', [
            'consent' => 'granted',
            'return_path' => 'https://untrusted.example/redirect',
        ])->assertSessionHasErrors('return_path');

        $this->post('/tracking-consent', [
            'consent' => 'granted',
            'return_path' => '/\\untrusted.example/redirect',
        ])->assertSessionHasErrors('return_path');
    }

    public function test_production_tracking_requires_all_privacy_configuration(): void
    {
        config()->set('app.env', 'production');
        config()->set('services.linkpilot.analytics.privacy_url', null);
        config()->set('services.linkpilot.analytics.hash_key', null);
        config()->set('services.linkpilot.analytics.hash_key_version', null);

        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create([
            'handle' => 'production-private',
            'is_published' => true,
        ]);

        $this->withCookie('linkpilot_tracking_consent', 'granted')
            ->get("/bio/{$page->handle}")
            ->assertOk();

        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_analytics_pruning_removes_only_events_older_than_retention(): void
    {
        $owner = User::factory()->create();
        $expired = $this->eventFor($owner, now('UTC')->subDays(91));
        $retained = $this->eventFor($owner, now('UTC')->subDays(89));

        $this->artisan('linkpilot:prune-analytics')
            ->expectsOutput('Pruned 1 analytics events.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('analytics_events', ['id' => $expired->id]);
        $this->assertDatabaseHas('analytics_events', ['id' => $retained->id]);
    }

    public function test_deleting_content_preserves_retained_analytics_reference_ids(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create();
        $bioLink = BioLink::factory()->for($page)->create();
        $event = $this->eventFor($owner, now('UTC'), [
            'bio_page_id' => $page->id,
            'bio_link_id' => $bioLink->id,
        ]);

        $bioLink->delete();
        $page->delete();

        $event = $event->fresh();

        $this->assertSame($page->id, $event->bio_page_id);
        $this->assertSame($bioLink->id, $event->bio_link_id);
    }

    /**
     * @param  array<string, int|null>  $references
     */
    private function eventFor(User $user, \DateTimeInterface $occurredAt, array $references = []): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'event_name' => 'bio_page.view',
            'occurred_at' => $occurredAt,
            'visitor_hash' => hash('sha256', Str::random()),
            'visitor_key_version' => 'test-v1',
            ...$references,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function linkPayload(Link $link, array $overrides = []): array
    {
        return array_merge([
            'destination_url' => $link->destination_url,
            'title' => $link->title ?? '',
            'slug' => $link->slug,
            'is_active' => $link->is_active,
            'folder_id' => $link->folder_id,
            'custom_domain_id' => $link->custom_domain_id,
            'available_from' => $link->available_from?->toISOString(),
            'expires_at' => $link->expires_at?->toISOString(),
            'access_code' => '',
            'clear_access_code' => false,
        ], $overrides);
    }
}
