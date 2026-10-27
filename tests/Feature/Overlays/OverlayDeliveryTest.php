<?php

namespace Tests\Feature\Overlays;

use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OverlayDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_and_delete_an_overlay(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)
            ->post('/dashboard/link-overlays', $this->overlayPayload())
            ->assertRedirect('/dashboard/link-overlays');

        $overlay = LinkOverlay::query()->sole();

        $this->assertDatabaseHas('link_overlays', [
            'id' => $overlay->id,
            'user_id' => $owner->id,
            'name' => 'Launch overlay',
            'heading' => 'Before you go',
            'cta_label' => 'Continue',
            'is_active' => true,
        ]);

        $this->actingAs($other)
            ->patch("/dashboard/link-overlays/{$overlay->id}", $this->overlayPayload())
            ->assertNotFound();

        $this->actingAs($other)
            ->delete("/dashboard/link-overlays/{$overlay->id}")
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch("/dashboard/link-overlays/{$overlay->id}", $this->overlayPayload([
                'heading' => 'One more thing',
                'is_active' => false,
            ]))
            ->assertRedirect('/dashboard/link-overlays');

        $this->assertDatabaseHas('link_overlays', [
            'id' => $overlay->id,
            'heading' => 'One more thing',
            'is_active' => false,
        ]);

        $this->actingAs($owner)
            ->delete("/dashboard/link-overlays/{$overlay->id}")
            ->assertRedirect('/dashboard/link-overlays');

        $this->assertDatabaseMissing('link_overlays', ['id' => $overlay->id]);
    }

    public function test_link_selects_only_a_same_owner_active_overlay(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownedActive = $this->createOverlay($owner);
        $ownedInactive = $this->createOverlay($owner, ['is_active' => false]);
        $foreignActive = $this->createOverlay($other);
        $link = Link::factory()->for($owner)->create(['slug' => 'overlay-assign']);

        $this->actingAs($owner)
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link, [
                'delivery_mode' => 'overlay',
                'link_overlay_id' => $ownedActive->id,
            ]))
            ->assertRedirect('/dashboard/links');

        $this->assertSame($ownedActive->id, $link->refresh()->link_overlay_id);

        $this->actingAs($owner)
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link, [
                'delivery_mode' => 'overlay',
                'link_overlay_id' => $foreignActive->id,
            ]))
            ->assertSessionHasErrors('link_overlay_id');

        $this->actingAs($owner)
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link, [
                'delivery_mode' => 'overlay',
                'link_overlay_id' => $ownedInactive->id,
            ]))
            ->assertSessionHasErrors('link_overlay_id');
    }

    public function test_overlay_render_shows_interstitial_without_click_then_records_consented_view(): void
    {
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner);
        $pixel = $this->createPixel($owner);
        $this->actingAs($owner)->post("/dashboard/pixels/{$pixel->id}/rules", [
            'target_type' => 'link_overlay', 'target_id' => $overlay->id, 'trigger' => 'page_view',
        ])->assertRedirect();
        $link = Link::factory()->for($owner)->create([
            'slug' => 'overlay-view',
            'destination_url' => 'https://example.com/dest',
            'link_overlay_id' => $overlay->id,
        ]);

        $this->withoutVite();

        $this
            ->get('/overlay-view')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/overlay', false)
                ->where('overlay.heading', 'Before you go')
                ->where('overlay.ctaLabel', 'Continue')
                ->where('pixels', ['page_view' => [], 'button_click' => [], 'lead_submit' => []]),
            );

        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseCount('analytics_events', 0);

        $this
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/overlay-view')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pixels', [
                    'page_view' => [['provider' => 'meta_pixel', 'identifier' => $pixel->provider_id]],
                    'button_click' => [],
                    'lead_submit' => [],
                ]),
            );

        $this->assertDatabaseCount('clicks', 0);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'overlay.view',
            'link_id' => $link->id,
            'link_overlay_id' => $overlay->id,
        ]);
    }

    public function test_overlay_view_records_event_when_resources_have_different_creators_in_one_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->members()->attach($member, ['role' => 'member']);
        $overlay = LinkOverlay::factory()->create([
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);
        $link = Link::factory()->for($owner)->create([
            'slug' => 'shared-overlay-view',
            'workspace_id' => $workspace->id,
            'link_overlay_id' => $overlay->id,
        ]);

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/shared-overlay-view')
            ->assertOk();

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'link_id' => $link->id,
            'link_overlay_id' => $overlay->id,
            'event_name' => 'overlay.view',
        ]);
    }

    public function test_continue_records_one_click_and_consented_event_then_redirects(): void
    {
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner);
        $link = Link::factory()->for($owner)->create([
            'slug' => 'overlay-continue',
            'destination_url' => 'https://example.com/final',
            'link_overlay_id' => $overlay->id,
        ]);

        $this
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->post('/overlay-continue/continue')
            ->assertRedirect('https://example.com/final');

        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseHas('clicks', ['link_id' => $link->id]);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link.clicked',
            'link_id' => $link->id,
        ]);
    }

    public function test_continue_answers_inertia_visits_with_a_client_location_redirect(): void
    {
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner);
        Link::factory()->for($owner)->create([
            'slug' => 'overlay-inertia',
            'destination_url' => 'https://example.com/final',
            'link_overlay_id' => $overlay->id,
        ]);

        // The interstitial posts through Inertia, so the visitor only reaches the
        // destination when the server answers with the client-side location header.
        $this
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/overlay-inertia/continue')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://example.com/final');

        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_access_code_gates_overlay_render_and_continue(): void
    {
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner);
        Link::factory()->for($owner)->create([
            'slug' => 'overlay-locked',
            'destination_url' => 'https://example.com/secret',
            'access_code' => Hash::make('open-sesame'),
            'link_overlay_id' => $overlay->id,
        ]);

        $this->withoutVite();

        $this
            ->get('/overlay-locked')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/unlock', false),
            );

        $this
            ->post('/overlay-locked/continue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/unlock', false),
            );

        $this->assertDatabaseCount('clicks', 0);

        $this
            ->post('/overlay-locked/unlock', ['access_code' => 'open-sesame'])
            ->assertRedirect('/overlay-locked');

        $this
            ->get('/overlay-locked')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/overlay', false),
            );

        $this
            ->post('/overlay-locked/continue')
            ->assertRedirect('https://example.com/secret');

        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_inactive_overlay_falls_back_to_direct_delivery(): void
    {
        $owner = User::factory()->create();
        $overlay = $this->createOverlay($owner, ['is_active' => false]);
        Link::factory()->for($owner)->create([
            'slug' => 'overlay-inactive',
            'destination_url' => 'https://example.com/direct',
            'link_overlay_id' => $overlay->id,
        ]);

        $this
            ->get('/overlay-inactive')
            ->assertRedirect('https://example.com/direct');

        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_link_without_overlay_retains_direct_redirect(): void
    {
        Link::factory()->create([
            'slug' => 'direct-link',
            'destination_url' => 'https://example.com/plain',
        ]);

        $this
            ->get('/direct-link')
            ->assertRedirect('https://example.com/plain');

        $this->assertDatabaseCount('clicks', 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function overlayPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Launch overlay',
            'heading' => 'Before you go',
            'body' => 'Check out the launch details.',
            'cta_label' => 'Continue',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOverlay(User $user, array $overrides = []): LinkOverlay
    {
        return $user->currentWorkspace->linkOverlays()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Overlay',
            'heading' => 'Before you go',
            'body' => 'Overlay body.',
            'cta_label' => 'Continue',
            'is_active' => true,
        ], $overrides));
    }

    private function createPixel(User $user): TrackingPixel
    {
        return $user->currentWorkspace->trackingPixels()->create([
            'user_id' => $user->id,
            'name' => 'Launch pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '123456789012345',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function linkPayload(Link $link, array $overrides = []): array
    {
        return array_merge([
            'destination_url' => $link->destination_url,
            'title' => $link->title ?? '',
            'slug' => $link->slug,
            'is_active' => $link->is_active,
        ], $overrides);
    }
}
