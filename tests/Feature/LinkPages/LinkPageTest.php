<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Plan;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_update_and_delete_a_validated_tracking_pixel(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->makePro($owner);

        $this->actingAs($owner)
            ->post('/tracking-pixels', $this->pixelPayload())
            ->assertRedirect('/tracking-pixels');

        $pixel = TrackingPixel::query()->sole();

        $this->assertDatabaseHas('tracking_pixels', [
            'id' => $pixel->id,
            'user_id' => $owner->id,
            'name' => 'Launch Meta Pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '123456789012345',
            'is_active' => true,
        ]);

        $this->actingAs($otherUser)
            ->patch("/tracking-pixels/{$pixel->id}", $this->pixelPayload())
            ->assertForbidden();

        $this->actingAs($owner)
            ->patch("/tracking-pixels/{$pixel->id}", $this->pixelPayload([
                'name' => 'Launch analytics',
                'provider' => 'google_analytics',
                'provider_id' => 'G-AB12CD34',
                'is_active' => false,
            ]))
            ->assertRedirect('/tracking-pixels');

        $this->assertDatabaseHas('tracking_pixels', [
            'id' => $pixel->id,
            'name' => 'Launch analytics',
            'provider' => 'google_analytics',
            'provider_id' => 'G-AB12CD34',
            'is_active' => false,
        ]);

        $this->actingAs($otherUser)
            ->delete("/tracking-pixels/{$pixel->id}")
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete("/tracking-pixels/{$pixel->id}")
            ->assertRedirect('/tracking-pixels');

        $this->assertDatabaseMissing('tracking_pixels', ['id' => $pixel->id]);
    }

    public function test_tracking_pixels_reject_unsupported_providers_and_invalid_identifiers(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);

        $this->actingAs($owner)
            ->post('/tracking-pixels', $this->pixelPayload([
                'provider' => 'google_tag_manager',
                'provider_id' => 'GTM-AB12CD34',
            ]))
            ->assertSessionHasErrors('provider');

        $this->actingAs($owner)
            ->post('/tracking-pixels', $this->pixelPayload([
                'provider' => 'meta_pixel',
                'provider_id' => 'not-a-meta-id',
            ]))
            ->assertSessionHasErrors('provider_id');

        $this->actingAs($owner)
            ->post('/tracking-pixels', $this->pixelPayload([
                'provider' => 'google_analytics',
                'provider_id' => 'GTM-AB12CD34',
            ]))
            ->assertSessionHasErrors('provider_id');

        $this->assertDatabaseCount('tracking_pixels', 0);
    }

    public function test_plan_limits_count_retained_pixels_and_link_pages_and_block_new_resources(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post('/tracking-pixels', $this->pixelPayload())
            ->assertSessionHasErrors('plan');

        $this->actingAs($owner)
            ->post('/link-pages', $this->pagePayload())
            ->assertRedirect('/link-pages/1');

        $this->actingAs($owner)
            ->post('/link-pages', $this->pagePayload(['slug' => 'second-campaign']))
            ->assertSessionHasErrors('plan');

        $this->withoutVite();

        $this->actingAs($owner)
            ->get('/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.pixels.used', 0)
                ->where('usage.pixels.limit', 0)
                ->where('usage.linkPages.used', 1)
                ->where('usage.linkPages.limit', 1),
            );
    }

    public function test_owner_can_manage_ordered_link_page_items_and_other_users_cannot_mutate_them(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $page = $this->createLinkPage($owner, $this->pagePayload());

        $first = $this->createItem($owner, $page, $this->itemPayload([
            'title' => 'First destination',
            'destination_url' => 'https://example.com/first',
        ]));
        $second = $this->createItem($owner, $page, $this->itemPayload([
            'title' => 'Second destination',
            'destination_url' => 'https://example.com/second',
        ]));

        $this->assertSame(0, $first->position);
        $this->assertSame(1, $second->position);

        $this->actingAs($owner)
            ->patch("/link-pages/{$page->id}/items/{$second->id}", $this->itemPayload([
                'title' => 'Updated destination',
                'destination_url' => 'https://example.com/updated',
                'is_active' => false,
            ]))
            ->assertRedirect("/link-pages/{$page->id}");

        $this->actingAs($owner)
            ->patch("/link-pages/{$page->id}/items/reorder", [
                'item_ids' => [$second->id, $first->id],
            ])
            ->assertRedirect("/link-pages/{$page->id}");

        $this->assertDatabaseHas('link_page_items', [
            'id' => $second->id,
            'title' => 'Updated destination',
            'position' => 0,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('link_page_items', [
            'id' => $first->id,
            'position' => 1,
        ]);

        $this->actingAs($otherUser)
            ->get("/link-pages/{$page->id}")
            ->assertForbidden();

        $this->actingAs($otherUser)
            ->patch("/link-pages/{$page->id}", $this->pagePayload())
            ->assertForbidden();

        $foreignPage = $this->createLinkPage($otherUser, $this->pagePayload([
            'slug' => 'other-campaign',
        ]));
        $foreignItem = $this->createItem($otherUser, $foreignPage);

        $this->actingAs($owner)
            ->patch("/link-pages/{$page->id}/items/{$foreignItem->id}", $this->itemPayload())
            ->assertNotFound();
    }

    public function test_link_pages_only_attach_up_to_five_active_pixels_owned_by_the_same_user(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);

        $pixels = collect(range(1, 6))->map(fn (int $index): TrackingPixel => $this->createPixel(
            $owner,
            $this->pixelPayload([
                'name' => "Pixel {$index}",
                'provider_id' => "12345678901234{$index}",
            ]),
        ));

        $this->actingAs($owner)
            ->post('/link-pages', $this->pagePayload([
                'slug' => 'too-many-pixels',
                'tracking_pixel_ids' => $pixels->pluck('id')->all(),
            ]))
            ->assertSessionHasErrors('tracking_pixel_ids');

        $inactivePixel = $this->createPixel($owner, $this->pixelPayload([
            'name' => 'Inactive pixel',
            'provider_id' => '987654321098765',
            'is_active' => false,
        ]));

        $this->actingAs($owner)
            ->post('/link-pages', $this->pagePayload([
                'slug' => 'inactive-pixel',
                'tracking_pixel_ids' => [$inactivePixel->id],
            ]))
            ->assertSessionHasErrors('tracking_pixel_ids');

        $otherUser = User::factory()->create();
        $this->makePro($otherUser);
        $foreignPixel = $this->createPixel($otherUser, $this->pixelPayload([
            'name' => 'Foreign pixel',
            'provider_id' => '456789012345678',
        ]));

        $this->actingAs($owner)
            ->post('/link-pages', $this->pagePayload([
                'slug' => 'foreign-pixel',
                'tracking_pixel_ids' => [$foreignPixel->id],
            ]))
            ->assertSessionHasErrors('tracking_pixel_ids');

        $page = $this->createLinkPage($owner, $this->pagePayload([
            'slug' => 'five-pixels',
            'tracking_pixel_ids' => $pixels->take(5)->pluck('id')->all(),
        ]));

        $this->assertCount(5, $page->trackingPixels()->get());
    }

    public function test_public_link_pages_hide_pixels_until_consent_then_emit_safe_props_and_view_event(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);
        $metaPixel = $this->createPixel($owner);
        $analyticsPixel = $this->createPixel($owner, $this->pixelPayload([
            'name' => 'Launch analytics',
            'provider' => 'google_analytics',
            'provider_id' => 'G-AB12CD34',
        ]));
        $page = $this->createLinkPage($owner, $this->pagePayload([
            'tracking_pixel_ids' => [$metaPixel->id, $analyticsPixel->id],
        ]));
        $item = $this->createItem($owner, $page);
        $this->createItem($owner, $page, $this->itemPayload([
            'title' => 'Hidden item',
            'destination_url' => 'https://example.com/hidden',
            'is_active' => false,
        ]));

        $this->withoutVite();

        $this->get('/pages/launch-campaign')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('link-pages/public', false)
                ->where('title', 'Launch campaign')
                ->where('slug', 'launch-campaign')
                ->has('items', 1)
                ->where('items.0.title', 'Start here')
                ->where('items.0.url', "/pages/launch-campaign/items/{$item->id}")
                ->has('pixels', 0)
                ->where('trackingConsent', null),
            )
            ->assertDontSee($metaPixel->provider_id)
            ->assertDontSee('https://example.com/start');

        $this->assertDatabaseCount('analytics_events', 0);

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/pages/launch-campaign')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->has('pixels', 2)
                ->where('pixels.0.provider', 'meta_pixel')
                ->where('pixels.0.identifier', '123456789012345')
                ->where('pixels.1.provider', 'google_analytics')
                ->where('pixels.1.identifier', 'G-AB12CD34')
                ->where('trackingConsent', 'granted'),
            );

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $owner->id,
            'event_name' => 'link_page.view',
            'link_page_id' => $page->id,
        ]);
    }

    public function test_public_link_page_item_redirects_and_records_only_consented_exact_references(): void
    {
        $owner = User::factory()->create();
        $page = $this->createLinkPage($owner, $this->pagePayload());
        $item = $this->createItem($owner, $page, $this->itemPayload([
            'destination_url' => 'https://example.com/campaign-destination',
        ]));
        $inactiveItem = $this->createItem($owner, $page, $this->itemPayload([
            'title' => 'Inactive destination',
            'destination_url' => 'https://example.com/inactive',
            'is_active' => false,
        ]));

        $this->get("/pages/launch-campaign/items/{$item->id}")
            ->assertRedirect('https://example.com/campaign-destination');

        $this->assertDatabaseCount('analytics_events', 0);

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/pages/launch-campaign/items/{$item->id}")
            ->assertRedirect('https://example.com/campaign-destination');

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $owner->id,
            'event_name' => 'link_page.clicked',
            'link_page_id' => $page->id,
            'link_page_item_id' => $item->id,
        ]);

        $this->get("/pages/launch-campaign/items/{$inactiveItem->id}")
            ->assertNotFound();

        $page->update(['is_published' => false]);

        $this->get('/pages/launch-campaign')->assertNotFound();
        $this->get('/pages/missing-campaign')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pixelPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Launch Meta Pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '123456789012345',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pagePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Launch campaign',
            'slug' => 'launch-campaign',
            'title' => 'Launch campaign',
            'description' => 'A focused set of launch destinations.',
            'is_published' => true,
            'tracking_pixel_ids' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Start here',
            'destination_url' => 'https://example.com/start',
            'is_active' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPixel(User $user, array $attributes = []): TrackingPixel
    {
        $this->actingAs($user)
            ->post('/tracking-pixels', $attributes ?: $this->pixelPayload())
            ->assertRedirect('/tracking-pixels');

        return TrackingPixel::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLinkPage(User $user, array $attributes = []): LinkPage
    {
        $this->actingAs($user)
            ->post('/link-pages', $attributes ?: $this->pagePayload())
            ->assertRedirect();

        return LinkPage::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createItem(User $user, LinkPage $page, array $attributes = []): LinkPageItem
    {
        $this->actingAs($user)
            ->post("/link-pages/{$page->id}/items", $attributes ?: $this->itemPayload())
            ->assertRedirect("/link-pages/{$page->id}");

        return LinkPageItem::query()
            ->where('link_page_id', $page->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function makePro(User $user): void
    {
        $user->subscription()->update([
            'plan_id' => Plan::query()->where('code', 'pro')->sole()->id,
        ]);
    }
}
