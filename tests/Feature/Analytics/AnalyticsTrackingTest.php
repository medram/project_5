<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\Settings\PlatformSettings;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_consented_direct_link_records_one_click_and_one_exact_analytics_event(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create([
            'slug' => 'consented-direct',
            'destination_url' => 'https://example.com/consented-direct',
        ]);

        $this->withCookie(TrackingConsent::COOKIE_NAME, 'granted')
            ->get('/consented-direct')
            ->assertRedirect('https://example.com/consented-direct');

        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseHas('clicks', ['link_id' => $link->id]);
        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $owner->id,
            'link_id' => $link->id,
            'event_name' => 'link.clicked',
        ]);
    }

    public function test_consented_bio_views_and_clicks_store_only_minimal_exact_references(): void
    {
        $analyticsKey = str_repeat('k', 32);
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'analytics', [
            'visitor_hash_key' => $analyticsKey,
            'visitor_hash_version' => 'test-v1',
        ], $settings->version('analytics'));
        config()->set('services.linkpilot.analytics.hash_key', $analyticsKey);
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
                ->where('presentation.items.0.url', "/bio/consented-creator/links/{$bioLink->id}"),
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

    public function test_analytics_event_requires_a_workspace_and_at_least_one_valid_resource_reference(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->firstOrFail();
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $base = [
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'event_name' => 'link.clicked',
            'occurred_at' => now('UTC'),
            'visitor_hash' => hash('sha256', Str::random()),
            'visitor_key_version' => 'test-v1',
        ];

        foreach ([
            $base + ['workspace_id' => $workspace->id],
            $base + ['link_id' => 999999, 'workspace_id' => $workspace->id],
            $base + ['link_id' => $link->id],
        ] as $attributes) {
            try {
                AnalyticsEvent::query()->create($attributes);
                $this->fail('Analytics events need valid resource references and a matching workspace snapshot.');
            } catch (\LogicException) {
                $this->assertDatabaseCount('analytics_events', 0);
            } catch (QueryException) {
                // Invalid foreign keys are also rejected by the database.
            }
        }
    }

    public function test_event_workspace_snapshot_comes_from_resource_not_current_workspace(): void
    {
        $owner = User::factory()->create();
        $originalWorkspace = $owner->personalWorkspace()->firstOrFail();
        $secondWorkspace = Workspace::query()->create([
            'name' => 'Second workspace',
            'slug' => 'second-'.$owner->id,
            'owner_id' => $owner->id,
        ]);
        $secondWorkspace->members()->attach($owner, ['role' => 'owner']);
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $originalWorkspace->id,
        ]);
        $this->app->make(WorkspaceContext::class)
            ->activate($owner, $secondWorkspace);

        $request = Request::create('/', 'GET', server: ['HTTP_USER_AGENT' => 'Chrome/120']);
        $request->cookies->set(TrackingConsent::COOKIE_NAME, 'granted');
        $event = app(AnalyticsTracker::class)->record(
            $request,
            'link.clicked',
            $owner,
            ['link' => $link],
        );

        $this->assertNotNull($event);
        $this->assertSame($originalWorkspace->id, $event->workspace_id);
        $this->assertSame($owner->id, $event->user_id);
        $this->expectException(\LogicException::class);
        $event->forceFill(['workspace_id' => $secondWorkspace->id])->save();
    }

    public function test_event_rejects_references_from_another_workspace_even_for_same_user(): void
    {
        $owner = User::factory()->create();
        $firstWorkspace = $owner->personalWorkspace()->firstOrFail();
        $secondWorkspace = Workspace::query()->create([
            'name' => 'Second workspace',
            'slug' => 'second-'.$owner->id,
            'owner_id' => $owner->id,
        ]);
        $secondWorkspace->members()->attach($owner, ['role' => 'owner']);
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $firstWorkspace->id,
        ]);
        $bioPage = BioPage::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $secondWorkspace->id,
        ]);

        try {
            $request = Request::create('/', 'GET');
            $request->cookies->set(TrackingConsent::COOKIE_NAME, 'granted');
            app(AnalyticsTracker::class)->record(
                $request,
                'overlay.view',
                $owner,
                ['link' => $link, 'bioPage' => $bioPage],
            );
            $this->fail('Mixed-workspace references must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Analytics references must belong to one workspace.', $exception->getMessage());
        }

        $this->assertDatabaseCount('analytics_events', 0);
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
        $directLink = Link::factory()->for($owner)->create([
            'slug' => 'resilient-direct',
            'destination_url' => 'https://example.com/resilient-direct',
        ]);
        $tracker = new class extends AnalyticsTracker
        {
            public int $calls = 0;

            public function __construct() {}

            public function canTrack(Request $request): bool
            {
                return true;
            }

            /**
             * @param  array<string, mixed>  $references
             */
            public function record(
                Request $request,
                string $eventName,
                ?User $user,
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
        $this->withCookie(TrackingConsent::COOKIE_NAME, 'granted')
            ->get('/resilient-direct')
            ->assertRedirect('https://example.com/resilient-direct');

        $this->assertDatabaseHas('clicks', ['link_id' => $directLink->id]);

        $this->assertSame(3, $tracker->calls);
    }

    public function test_account_analytics_are_scoped_aggregated_and_zero_filled_for_selected_utc_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $firstLink = Link::factory()->for($owner)->create();
        $secondLink = Link::factory()->for($owner)->create();
        $otherOwner = User::factory()->create();
        $otherLink = Link::factory()->for($otherOwner)->create();
        $windowStart = Carbon::parse('2026-09-20 00:00:00', 'UTC');
        $today = Carbon::parse('2026-10-19 10:00:00', 'UTC');

        Click::factory()->for($firstLink)->create(['occurred_at' => $windowStart]);
        Click::factory()->for($firstLink)->create(['occurred_at' => $today]);
        Click::factory()->for($secondLink)->create(['occurred_at' => $today]);
        Click::factory()->for($firstLink)->create([
            'occurred_at' => $windowStart->copy()->subSecond(),
        ]);
        Click::factory()->for($otherLink)->create(['occurred_at' => $today]);

        $this->eventFor($owner, $windowStart, [
            'link_id' => $firstLink->id,
            'event_name' => 'overlay.view',
            'visitor_hash' => str_repeat('b', 64),
            'browser' => 'Firefox',
            'platform' => 'macOS',
        ]);
        foreach ([$firstLink, $secondLink] as $link) {
            $this->eventFor($owner, $today, [
                'link_id' => $link->id,
                'event_name' => 'link.clicked',
                'visitor_hash' => str_repeat('a', 64),
                'referrer_host' => 'campaign.example',
                'browser' => 'Chrome',
                'platform' => 'Linux',
            ]);
        }
        $this->eventFor($owner, $windowStart->copy()->subSecond(), [
            'link_id' => $firstLink->id,
            'event_name' => 'bio_page.view',
            'visitor_hash' => str_repeat('c', 64),
            'referrer_host' => 'older.example',
            'browser' => 'Safari',
            'platform' => 'iOS',
        ]);
        $this->eventFor($otherOwner, $today, [
            'link_id' => $otherLink->id,
            'event_name' => 'link.clicked',
            'visitor_hash' => str_repeat('d', 64),
            'referrer_host' => 'foreign.example',
            'browser' => 'Edge',
            'platform' => 'Windows',
        ]);

        $this->withoutVite();

        $this->actingAs($owner)
            ->get('/dashboard/insights')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/index')
                ->where('analytics.days', 30)
                ->where('analytics.totalClicks', 3)
                ->where('analytics.uniqueVisitors', 2)
                ->where('analytics.totalEvents', 3)
                ->has('analytics.daily', 30)
                ->where('analytics.daily.0', [
                    'date' => '2026-09-20',
                    'clicks' => 1,
                    'events' => 1,
                ])
                ->where('analytics.daily.1', [
                    'date' => '2026-09-21',
                    'clicks' => 0,
                    'events' => 0,
                ])
                ->where('analytics.daily.29', [
                    'date' => '2026-10-19',
                    'clicks' => 2,
                    'events' => 2,
                ])
                ->where('analytics.eventsByName', [
                    ['name' => 'link.clicked', 'count' => 2],
                    ['name' => 'overlay.view', 'count' => 1],
                ])
                ->where('analytics.topReferrers', [
                    ['name' => 'campaign.example', 'count' => 2],
                ])
                ->where('analytics.topBrowsers', [
                    ['name' => 'Chrome', 'count' => 2],
                    ['name' => 'Firefox', 'count' => 1],
                ])
                ->where('analytics.topPlatforms', [
                    ['name' => 'Linux', 'count' => 2],
                    ['name' => 'macOS', 'count' => 1],
                ]));

        foreach ([7 => 2, 90 => 4] as $days => $expectedTotal) {
            $this->actingAs($owner)
                ->get("/dashboard/insights?days={$days}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('analytics.days', $days)
                    ->where('analytics.totalClicks', $expectedTotal)
                    ->where('analytics.totalEvents', $expectedTotal)
                    ->has('analytics.daily', $days));
        }

        $this->actingAs($owner)
            ->get('/dashboard/insights?days=14')
            ->assertRedirect()
            ->assertSessionHasErrors('days');
    }

    public function test_empty_account_analytics_keep_the_selected_window_and_real_empty_states(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00', 'UTC'));
        $owner = User::factory()->create();

        $this->withoutVite();

        $this->actingAs($owner)
            ->get('/dashboard/insights?days=7')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.days', 7)
                ->where('analytics.totalClicks', 0)
                ->where('analytics.uniqueVisitors', 0)
                ->where('analytics.totalEvents', 0)
                ->has('analytics.daily', 7)
                ->where('analytics.daily.0', [
                    'date' => '2026-10-13',
                    'clicks' => 0,
                    'events' => 0,
                ])
                ->where('analytics.daily.6', [
                    'date' => '2026-10-19',
                    'clicks' => 0,
                    'events' => 0,
                ])
                ->where('analytics.eventsByName', [])
                ->where('analytics.topReferrers', [])
                ->where('analytics.topBrowsers', [])
                ->where('analytics.topPlatforms', []));
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
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link, [
                'access_code' => 'second-code',
            ]))
            ->assertRedirect('/dashboard/links');

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
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link->refresh(), [
                'clear_access_code' => true,
            ]))
            ->assertRedirect('/dashboard/links');

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

    public function test_access_code_failure_limit_expires_after_five_minutes(): void
    {
        Link::factory()->create([
            'slug' => 'expiring-throttle',
            'access_code' => Hash::make('correct-code'),
            'access_code_version' => 1,
        ]);

        $this->travelTo(now());

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/expiring-throttle/unlock', ['access_code' => 'wrong-code'])
                ->assertSessionHasErrors('access_code');
        }

        $this->post('/expiring-throttle/unlock', ['access_code' => 'wrong-code'])
            ->assertTooManyRequests();

        $this->travel(299)->seconds();

        $this->post('/expiring-throttle/unlock', ['access_code' => 'wrong-code'])
            ->assertTooManyRequests();

        $this->travel(1)->seconds();

        $this->post('/expiring-throttle/unlock', ['access_code' => 'wrong-code'])
            ->assertSessionHasErrors('access_code');
    }

    public function test_access_code_failure_limit_is_isolated_by_client_ip(): void
    {
        Link::factory()->create([
            'slug' => 'ip-isolated-throttle',
            'access_code' => Hash::make('correct-code'),
            'access_code_version' => 1,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->post('/ip-isolated-throttle/unlock', ['access_code' => 'wrong-code'])
                ->assertSessionHasErrors('access_code');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post('/ip-isolated-throttle/unlock', ['access_code' => 'wrong-code'])
            ->assertTooManyRequests();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.21'])
            ->post('/ip-isolated-throttle/unlock', ['access_code' => 'wrong-code'])
            ->assertSessionHasErrors('access_code');
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
        $link = Link::factory()->for($owner)->create();
        $expired = $this->eventFor($owner, now('UTC')->subDays(91), ['link_id' => $link->id]);
        $retained = $this->eventFor($owner, now('UTC')->subDays(89), ['link_id' => $link->id]);

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
     * @param  array<string, mixed>  $attributes
     */
    private function eventFor(User $user, \DateTimeInterface $occurredAt, array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $user->current_workspace_id,
            'user_id' => $user->id,
            'event_name' => 'bio_page.view',
            'occurred_at' => $occurredAt,
            'visitor_hash' => hash('sha256', Str::random()),
            'visitor_key_version' => 'test-v1',
        ], $attributes));
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
