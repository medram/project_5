<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use App\Services\Analytics\GeoIpCountryResolver;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdvancedAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_local_geoip_database_returns_no_country(): void
    {
        config()->set(
            'services.linkpilot.analytics.geoip_database',
            storage_path('app/nonexistent-country-test.mmdb'),
        );

        $this->assertNull(app(GeoIpCountryResolver::class)->countryCode('192.0.2.1'));
    }

    public function test_corrupt_local_geoip_database_returns_no_country(): void
    {
        $databasePath = tempnam(sys_get_temp_dir(), 'linkpilot-invalid-geoip-');
        $this->assertIsString($databasePath);
        file_put_contents($databasePath, 'not a MaxMind database');

        try {
            config()->set('services.linkpilot.analytics.geoip_database', $databasePath);

            $this->assertNull(app(GeoIpCountryResolver::class)->countryCode('198.51.100.1'));
        } finally {
            @unlink($databasePath);
        }
    }

    public function test_consented_link_event_stores_bounded_allowlisted_utm_without_ip(): void
    {
        $link = Link::factory()->create([
            'slug' => 'analytics-dimensions',
            'destination_url' => 'https://example.com/dimensions',
        ]);

        $campaign = str_repeat('x', 600);

        $this->withCookie(TrackingConsent::COOKIE_NAME, 'granted')
            ->get('/analytics-dimensions?utm_source=newsletter&utm_medium=email&utm_campaign='.$campaign.'&utm_id=ignored')
            ->assertRedirect('https://example.com/dimensions');

        $event = AnalyticsEvent::query()->sole();

        $this->assertNull($event->country_code);
        $this->assertSame('newsletter', $event->utm_source);
        $this->assertSame('email', $event->utm_medium);
        $this->assertSame(512, strlen($event->utm_campaign));
        $this->assertNull($event->utm_term);
        $this->assertNull($event->utm_content);
        $this->assertArrayNotHasKey('ip_address', $event->getAttributes());
        $this->assertArrayNotHasKey('user_agent', $event->getAttributes());
        $this->assertArrayNotHasKey('destination_url', $event->getAttributes());
    }

    public function test_unconsented_link_request_does_not_persist_utm_dimensions(): void
    {
        Link::factory()->create([
            'slug' => 'no-consent-dimensions',
            'destination_url' => 'https://example.com/no-consent',
        ]);

        $this->get('/no-consent-dimensions?utm_source=campaign')
            ->assertRedirect('https://example.com/no-consent');

        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_analytics_filters_apply_to_summary_and_event_table(): void
    {
        $user = User::factory()->create();
        $link = Link::factory()->for($user)->create();
        $otherLink = Link::factory()->for($user)->create();
        $foreignLink = Link::factory()->create();

        Click::factory()->for($link)->create(['occurred_at' => now('UTC')]);
        Click::factory()->for($otherLink)->create(['occurred_at' => now('UTC')]);

        $matchingAttributes = [
            'event_name' => 'link.clicked',
            'occurred_at' => now('UTC'),
            'browser' => 'Chrome',
            'platform' => 'Linux',
            'country_code' => 'CA',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'summer',
            'utm_term' => 'enterprise',
            'utm_content' => 'banner',
        ];
        $matching = $this->createAnalyticsEvent($link, $matchingAttributes);
        foreach ([
            ['event_name' => 'overlay.view'],
            ['browser' => 'Safari'],
            ['platform' => 'Windows'],
            ['country_code' => 'US'],
            ['utm_source' => 'social'],
            ['utm_medium' => 'sms'],
            ['utm_campaign' => 'winter'],
            ['utm_term' => 'starter'],
            ['utm_content' => 'footer'],
        ] as $mismatch) {
            $this->createAnalyticsEvent($link, array_replace($matchingAttributes, $mismatch));
        }
        $this->createAnalyticsEvent($otherLink, $matchingAttributes);
        $this->createAnalyticsEvent($foreignLink, $matchingAttributes);
        $this->createAnalyticsEvent($link, array_replace($matchingAttributes, [
            'occurred_at' => now('UTC')->subDays(31),
        ]));

        Click::factory()->for($link)->create(['occurred_at' => now('UTC')->subDays(31)]);

        $filters = [
            'days' => 30,
            'resource_type' => 'link',
            'resource_id' => $link->id,
            'event_name' => 'link.clicked',
            'browser' => 'Chrome',
            'platform' => 'Linux',
            'country' => 'CA',
            'utm_source' => 'newsletter',
            'utm_medium' => 'email',
            'utm_campaign' => 'summer',
            'utm_term' => 'enterprise',
            'utm_content' => 'banner',
        ];
        $query = http_build_query($filters);

        $this->withoutVite();

        $this->actingAs($user)
            ->get('/dashboard/insights?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/index', false)
                ->where('analytics.totalClicks', 1)
                ->where('analytics.totalEvents', 1)
                ->where('analytics.uniqueVisitors', 1)
                ->where('analytics.topCountries.0.name', 'CA')
                ->where('filters.resource_type', 'link'));

        $this->actingAs($user)
            ->get('/dashboard/insights/events?'.$query)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/events', false)
                ->has('events.data', 1)
                ->where('events.data.0.uuid', $matching->uuid)
                ->missing('events.data.0.visitor_hash'));
    }

    public function test_foreign_resource_filter_returns_empty_analytics(): void
    {
        $user = User::factory()->create();
        $foreignLink = Link::factory()->create();
        $this->createAnalyticsEvent($foreignLink, ['country_code' => 'CA']);

        $filters = http_build_query([
            'days' => 30,
            'resource_type' => 'link',
            'resource_id' => $foreignLink->id,
        ]);

        $this->withoutVite();

        $this->actingAs($user)
            ->get('/dashboard/insights?'.$filters)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/index', false)
                ->where('analytics.totalClicks', 0)
                ->where('analytics.totalEvents', 0));

        $this->actingAs($user)
            ->get('/dashboard/insights/events?'.$filters)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/events', false)
                ->has('events.data', 0));
    }

    public function test_csv_reuses_filters_orders_rows_and_neutralizes_formula_cells(): void
    {
        $user = User::factory()->create();
        $link = Link::factory()->for($user)->create();
        $newer = $this->createAnalyticsEvent($link, [
            'occurred_at' => now('UTC')->subHour(),
            'browser' => 'Chrome',
            'country_code' => 'CA',
            'utm_source' => '=HYPERLINK("https://example.com","x")',
        ]);
        $older = $this->createAnalyticsEvent($link, [
            'occurred_at' => now('UTC')->subHours(2),
            'browser' => 'Chrome',
            'country_code' => 'CA',
            'utm_source' => 'newsletter',
        ]);
        $excluded = $this->createAnalyticsEvent($link, [
            'occurred_at' => now('UTC')->subMinutes(30),
            'browser' => 'Safari',
            'country_code' => 'CA',
            'utm_source' => 'newsletter',
        ]);
        $filters = http_build_query([
            'days' => 30,
            'resource_type' => 'link',
            'resource_id' => $link->id,
            'browser' => 'Chrome',
            'country' => 'CA',
        ]);

        $response = $this->actingAs($user)
            ->get('/dashboard/insights/export?'.$filters)
            ->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('visitor_hash', $csv);
        $this->assertStringNotContainsString($newer->visitor_hash, $csv);
        $this->assertStringNotContainsString($excluded->uuid, $csv);
        $this->assertLessThan(strpos($csv, $older->uuid), strpos($csv, $newer->uuid));
    }

    public function test_invalid_analytics_filters_return_validation_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/dashboard/insights?days=365')
            ->assertUnprocessable();

        $this->actingAs($user)
            ->getJson('/dashboard/insights?resource_type=link')
            ->assertUnprocessable();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAnalyticsEvent(Link $link, array $attributes = []): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $link->workspace_id,
            'user_id' => $link->user_id,
            'link_id' => $link->id,
            'event_name' => 'link.clicked',
            'occurred_at' => now('UTC'),
            'visitor_hash' => hash('sha256', (string) Str::uuid()),
            'visitor_key_version' => 'test-v1',
            ...$attributes,
        ]);
    }
}
