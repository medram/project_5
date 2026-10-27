<?php

namespace Tests\Feature\Overlays;

use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class OverlaySchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlay_start_is_inclusive_expiry_is_exclusive_and_unavailable_overlays_fall_back_to_direct_delivery(): void
    {
        $start = CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC');
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner, [
            'starts_at' => $start,
            'expires_at' => $start->addMinute(),
        ]);
        $link = $this->createOverlayLink($owner, $overlay, 'scheduled-overlay');
        $this->withoutVite();

        $this->travelTo($start->subSecond());
        $this->get('/'.$link->slug)->assertRedirect($link->destination_url);
        $this->assertDatabaseCount('clicks', 1);

        $this->travelTo($start);
        $this->get('/'.$link->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/overlay', false));
        $this->assertDatabaseCount('clicks', 1);

        $this->travelTo($start->addSeconds(59));
        $this->get('/'.$link->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/overlay', false));
        $this->assertDatabaseCount('clicks', 1);

        $this->travelTo($start->addMinute());
        $this->get('/'.$link->slug)->assertRedirect($link->destination_url);
        $this->assertDatabaseCount('clicks', 2);
    }

    public function test_overlay_editor_stores_utc_schedule_and_validates_order_and_delay_bounds(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post('/dashboard/link-overlays', $this->overlayPayload([
                'starts_at' => '2026-10-23T12:00:00Z',
                'expires_at' => '2026-10-23T12:01:00Z',
                'display_delay_seconds' => 30,
            ]))
            ->assertRedirect('/dashboard/link-overlays')
            ->assertSessionHasNoErrors();

        $overlay = LinkOverlay::query()->sole();
        $this->assertSame('2026-10-23 12:00:00', DB::table('link_overlays')->where('id', $overlay->id)->value('starts_at'));
        $this->assertSame('2026-10-23 12:01:00', DB::table('link_overlays')->where('id', $overlay->id)->value('expires_at'));
        $this->assertSame(30, (int) $overlay->display_delay_seconds);

        $this->actingAs($owner)
            ->patch('/dashboard/link-overlays/'.$overlay->id, $this->overlayPayload([
                'starts_at' => '2026-10-23T12:00:00Z',
                'expires_at' => '2026-10-23T11:59:59Z',
                'display_delay_seconds' => 61,
            ]))
            ->assertSessionHasErrors(['expires_at', 'display_delay_seconds']);

        $this->assertDatabaseHas('link_overlays', [
            'id' => $overlay->id,
            'starts_at' => '2026-10-23 12:00:00',
            'expires_at' => '2026-10-23 12:01:00',
            'display_delay_seconds' => 30,
        ]);
    }

    public function test_public_overlay_enforces_its_server_session_deadline_without_early_click_or_analytics(): void
    {
        $now = CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC');
        $this->travelTo($now);
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner, ['display_delay_seconds' => 5]);
        $link = $this->createOverlayLink($owner, $overlay, 'delayed-overlay');
        $this->withoutVite();

        $pageResponse = $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/'.$link->slug)
            ->assertOk();
        $pageResponse->assertInertia(fn (Assert $page) => $page
            ->component('links/overlay', false)
            ->where('remainingDelaySeconds', 5));
        $sessionCookie = $this->sessionCookieValue($pageResponse);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'overlay.view',
            'link_id' => $link->id,
        ]);
        $this->assertDatabaseCount('clicks', 0);

        $earlyResponse = $this->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/'.$link->slug.'/continue')
            ->assertRedirect('/'.$link->slug);
        $sessionCookie = $this->sessionCookieValue($earlyResponse);

        $this->withoutHeader('X-Inertia');
        $pageResponse = $this->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/'.$link->slug)
            ->assertOk();
        $pageResponse->assertInertia(fn (Assert $page) => $page
            ->component('links/overlay', false)
            ->where('remainingDelaySeconds', 5));
        $sessionCookie = $this->sessionCookieValue($pageResponse);

        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertDatabaseMissing('analytics_events', ['event_name' => 'link.clicked']);

        $this->travelTo($now->addSeconds(5));
        $finalResponse = $this->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/'.$link->slug.'/continue');
        $finalResponse->assertStatus(409)
            ->assertHeader('X-Inertia-Location', $link->destination_url);

        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link.clicked',
            'link_id' => $link->id,
        ]);
    }

    public function test_direct_early_continue_starts_a_link_and_overlay_bound_deadline(): void
    {
        $now = CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC');
        $this->travelTo($now);
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner, ['display_delay_seconds' => 5]);
        $link = $this->createOverlayLink($owner, $overlay, 'direct-post-overlay');
        $this->withoutVite();

        $earlyResponse = $this->withHeaders(['X-Inertia' => 'true'])
            ->post('/'.$link->slug.'/continue')
            ->assertRedirect('/'.$link->slug);
        $sessionCookie = $this->sessionCookieValue($earlyResponse);

        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);

        $this->withoutHeader('X-Inertia');
        $pageResponse = $this->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->get('/'.$link->slug)
            ->assertOk();
        $pageResponse->assertInertia(fn (Assert $page) => $page
            ->component('links/overlay', false)
            ->where('remainingDelaySeconds', 5));
        $sessionCookie = $this->sessionCookieValue($pageResponse);

        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);

        $this->travelTo($now->addSeconds(5));
        $this->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/'.$link->slug.'/continue')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', $link->destination_url);

        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_expired_overlay_continue_has_no_side_effect_and_a_fresh_public_request_falls_back_to_direct_delivery(): void
    {
        $expiry = CarbonImmutable::parse('2026-10-23 12:00:05', 'UTC');
        $this->travelTo($expiry->subSecond());
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner, ['expires_at' => $expiry]);
        $link = $this->createOverlayLink($owner, $overlay, 'expired-overlay-continue');
        $this->withoutVite();

        $pageResponse = $this->get('/'.$link->slug)->assertOk();
        $pageResponse->assertInertia(fn (Assert $page) => $page
            ->component('links/overlay', false));
        $sessionCookie = $this->sessionCookieValue($pageResponse);

        $this->travelTo($expiry);
        $this->withUnencryptedCookie(config('session.cookie'), $sessionCookie)
            ->post('/'.$link->slug.'/continue')
            ->assertNotFound();
        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);

        $this->get('/'.$link->slug)->assertRedirect($link->destination_url);
        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOverlay(User $owner, array $overrides = []): LinkOverlay
    {
        return LinkOverlay::factory()->for($owner)->create($overrides);
    }

    private function createOverlayLink(User $owner, LinkOverlay $overlay, string $slug): Link
    {
        return Link::factory()->for($owner)->create([
            'slug' => $slug,
            'destination_url' => 'https://example.com/'.$slug,
            'delivery_mode' => 'overlay',
            'link_overlay_id' => $overlay->id,
        ]);
    }

    /** @param TestResponse<Response> $response */
    private function sessionCookieValue(TestResponse $response): string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === config('session.cookie')) {
                return $cookie->getValue();
            }
        }

        $this->fail('The response did not set a session cookie.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function overlayPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Scheduled overlay',
            'heading' => 'Before you go',
            'body' => 'Check out the details.',
            'cta_label' => 'Continue',
            'is_active' => true,
            'starts_at' => '',
            'expires_at' => '',
            'display_delay_seconds' => 0,
        ], $overrides);
    }
}
