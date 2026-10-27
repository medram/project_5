<?php

namespace Tests\Feature\Links;

use App\Models\CustomDomain;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\User;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\Links\LinkDeliveryService;
use App\Services\Links\LinkRoutingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PublicLinkDeliveryControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_maximum_clicks_allows_the_last_click_then_blocks_without_more_side_effects(): void
    {
        $link = Link::factory()->create([
            'slug' => 'click-cap',
            'max_clicks' => 1,
        ]);

        $this->get('/click-cap')->assertRedirect($link->destination_url);
        $this->assertDatabaseCount('clicks', 1);

        $this->get('/click-cap')->assertNotFound();
        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseCount('analytics_events', 0);

        $this->assertDatabaseHas('links', ['id' => $link->id, 'click_count' => 1]);
    }

    public function test_archived_link_returns_not_found_without_recording_a_click(): void
    {
        Link::factory()->create([
            'slug' => 'archived-link',
            'archived_at' => now(),
        ]);

        $this->get('/archived-link')->assertNotFound();
        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_utm_and_custom_parameters_preserve_existing_query_values_and_fragment(): void
    {
        $link = Link::factory()->create([
            'slug' => 'query-merge',
            'destination_url' => 'https://example.com/landing?keep=one&repeat=first&repeat=second&utm_source=old&ref=old#section',
            'utm_parameters' => [
                'utm_source' => 'phase5',
                'utm_campaign' => 'launch campaign',
            ],
            'custom_parameters' => [
                'ref' => 'newsletter',
            ],
        ]);

        $this->get('/query-merge')->assertRedirect(
            'https://example.com/landing?keep=one&repeat=first&repeat=second&utm_source=phase5&utm_campaign=launch+campaign&ref=newsletter#section',
        );
        $this->assertDatabaseHas('clicks', ['link_id' => $link->id]);
    }

    public function test_link_page_delivery_records_one_click_before_redirecting_to_the_published_workspace_page(): void
    {
        $link = Link::factory()->create();
        $page = LinkPage::factory()->create([
            'user_id' => $link->user_id,
            'workspace_id' => $link->workspace_id,
            'is_published' => true,
        ]);
        $link->update([
            'delivery_mode' => 'link_page',
            'link_page_id' => $page->id,
        ]);

        $this->get('/'.$link->slug)
            ->assertRedirect(route('link-pages.public', ['slug' => $page->slug]));
        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'click_count' => 1]);
    }

    public function test_custom_domain_link_page_delivery_redirects_to_the_application_host(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $domain = $workspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'links.phase5.example.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'active',
        ]);
        $page = LinkPage::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'is_published' => true,
        ]);
        $link = Link::factory()->for($owner)->create([
            'slug' => 'custom-host-page',
            'custom_domain_id' => $domain->id,
            'delivery_mode' => 'link_page',
            'link_page_id' => $page->id,
        ]);
        $expected = rtrim((string) config('app.url'), '/')
            .route('link-pages.public', ['slug' => $page->slug], false);
        $this->withoutVite();

        $url = app('url');
        $url->forceRootUrl("http://{$domain->hostname}");

        try {
            $response = $this->get('/'.$link->slug);
        } finally {
            $url->forceRootUrl((string) config('app.url'));
        }

        $response->assertRedirect($expected);
        $this->assertDatabaseCount('clicks', 1);
        $this->get('/pages/'.$page->slug)->assertOk();
    }

    public function test_unpublished_or_foreign_link_page_targets_fail_before_click_side_effects(): void
    {
        $localLink = Link::factory()->create();
        $unpublished = LinkPage::factory()->create([
            'user_id' => $localLink->user_id,
            'workspace_id' => $localLink->workspace_id,
            'is_published' => false,
        ]);
        $localLink->update([
            'delivery_mode' => 'link_page',
            'link_page_id' => $unpublished->id,
        ]);

        $this->get('/'.$localLink->slug)->assertNotFound();
        $this->assertDatabaseCount('clicks', 0);

        $foreignLink = Link::factory()->create();
        $foreignPage = LinkPage::factory()->create([
            'user_id' => $foreignLink->user_id,
            'workspace_id' => $foreignLink->workspace_id,
            'is_published' => true,
        ]);
        $localLink->update(['link_page_id' => $foreignPage->id]);

        $this->get('/'.$localLink->slug)->assertNotFound();
        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_direct_delivery_rechecks_hosts_claimed_after_link_creation(): void
    {
        $owner = User::factory()->create();
        $destination = 'https://links.claimed.example.test/path';
        $this->actingAs($owner)
            ->post('/dashboard/links', [
                'destination_url' => $destination,
                'slug' => 'claimed-destination',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        $foreignOwner = User::factory()->create();
        $foreignOwner->currentWorkspace->customDomains()->create([
            'user_id' => $foreignOwner->id,
            'hostname' => 'links.claimed.example.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'active',
        ]);

        $this->get('/claimed-destination')->assertNotFound();
        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_final_delivery_conditional_write_rejects_a_stale_preflight_at_the_click_limit(): void
    {
        $link = Link::factory()->create([
            'max_clicks' => 1,
        ]);
        $staleLink = Link::query()->findOrFail($link->id);

        $this->get('/'.$link->slug)->assertRedirect($link->destination_url);
        try {
            app(LinkDeliveryService::class)->complete(
                Request::create('/'.$link->slug, 'GET'),
                $staleLink,
            );
            $this->fail('A stale delivery must not pass the atomic click-limit predicate.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'click_count' => 1]);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_final_conditional_write_rejects_a_click_limit_change_after_preflight(): void
    {
        $link = Link::factory()->create([
            'max_clicks' => 1,
        ]);
        $clickCountChangedAfterPreflight = false;

        $this->mock(LinkRoutingResolver::class, function ($mock) use ($link, &$clickCountChangedAfterPreflight): void {
            $mock->shouldReceive('target')
                ->once()
                ->andReturnUsing(function (Request $request, Link $resolvedLink) use ($link, &$clickCountChangedAfterPreflight): ?string {
                    $this->assertSame($link->id, $resolvedLink->id);
                    DB::table('links')
                        ->where('id', $link->id)
                        ->update(['click_count' => 1]);
                    $clickCountChangedAfterPreflight = true;

                    return null;
                });
        });

        $this->get('/'.$link->slug)->assertNotFound();

        $this->assertTrue($clickCountChangedAfterPreflight);
        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_overlay_continue_rechecks_click_limit_before_recording_a_click(): void
    {
        $overlay = LinkOverlay::factory()->create();
        $link = Link::factory()->create([
            'slug' => 'overlay-cap',
            'link_overlay_id' => $overlay->id,
            'max_clicks' => 1,
            'click_count' => 1,
        ]);
        $link->clicks()->create(['occurred_at' => now('UTC')->subSecond()]);

        $this->post('/overlay-cap/continue')->assertNotFound();
        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_splash_delivery_stays_linkpilot_controlled_until_continue(): void
    {
        $link = Link::factory()->create([
            'slug' => 'splash-route',
            'delivery_mode' => 'splash',
        ]);

        $this->withoutVite();
        $this->get('/splash-route')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/splash', false)
                ->where('slug', 'splash-route'),
            );

        $this->assertDatabaseCount('clicks', 0);
        $this->post('/splash-route/continue')->assertRedirect($link->destination_url);
        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_click_row_failure_rolls_back_the_materialized_counter(): void
    {
        $link = Link::factory()->create(['slug' => 'click-write-failure']);
        DB::unprepared("CREATE TRIGGER reject_click_insert BEFORE INSERT ON clicks BEGIN SELECT RAISE(ABORT, 'click insert rejected'); END;");

        $this->get('/click-write-failure')->assertInternalServerError();

        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'click_count' => 0]);
    }

    public function test_post_commit_analytics_failure_does_not_suppress_redirect(): void
    {
        $link = Link::factory()->create(['slug' => 'analytics-down']);
        $tracker = $this->mock(AnalyticsTracker::class);
        $tracker->shouldReceive('record')->once()->andThrow(new RuntimeException('telemetry unavailable'));

        $this->get('/analytics-down')->assertRedirect($link->destination_url);

        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'click_count' => 1]);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_overlay_view_keeps_consented_view_analytics_without_counting_a_click(): void
    {
        config()->set('services.linkpilot.analytics.hash_key', 'test-analytics-key');
        config()->set('services.linkpilot.analytics.hash_key_version', 'test-v1');
        $overlay = LinkOverlay::factory()->create();
        $link = Link::factory()->create([
            'user_id' => $overlay->user_id,
            'workspace_id' => $overlay->workspace_id,
            'slug' => 'overlay-view',
            'delivery_mode' => 'overlay',
            'link_overlay_id' => $overlay->id,
        ]);

        $this->withoutVite()
            ->withCookie(TrackingConsent::COOKIE_NAME, 'granted')
            ->get('/overlay-view')
            ->assertOk();

        $this->assertDatabaseHas('analytics_events', [
            'link_id' => $link->id,
            'event_name' => 'overlay.view',
        ]);
        $this->assertDatabaseCount('clicks', 0);

        $this->post('/overlay-view/continue')->assertRedirect($link->destination_url);
        $this->assertDatabaseCount('clicks', 1);
    }
}
