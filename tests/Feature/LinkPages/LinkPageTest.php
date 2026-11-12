<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Plan;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            ->post('/dashboard/pixels', $this->pixelPayload())
            ->assertRedirect('/dashboard/pixels');

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
            ->patch("/dashboard/pixels/{$pixel->id}", $this->pixelPayload())
            ->assertNotFound();

        $this->actingAs($owner)
            ->patch("/dashboard/pixels/{$pixel->id}", $this->pixelPayload([
                'name' => 'Launch analytics',
                'provider' => 'google_analytics',
                'provider_id' => 'G-AB12CD34',
                'is_active' => false,
            ]))
            ->assertRedirect('/dashboard/pixels');

        $this->assertDatabaseHas('tracking_pixels', [
            'id' => $pixel->id,
            'name' => 'Launch analytics',
            'provider' => 'google_analytics',
            'provider_id' => 'G-AB12CD34',
            'is_active' => false,
        ]);

        $this->actingAs($otherUser)
            ->delete("/dashboard/pixels/{$pixel->id}")
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete("/dashboard/pixels/{$pixel->id}")
            ->assertRedirect('/dashboard/pixels');

        $this->assertDatabaseMissing('tracking_pixels', ['id' => $pixel->id]);
    }

    public function test_tracking_pixels_reject_unsupported_providers_and_invalid_identifiers(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);

        $this->actingAs($owner)
            ->post('/dashboard/pixels', $this->pixelPayload([
                'provider' => 'google_tag_manager',
                'provider_id' => 'GTM-AB12CD34',
            ]))
            ->assertSessionHasErrors('provider');

        $this->actingAs($owner)
            ->post('/dashboard/pixels', $this->pixelPayload([
                'provider' => 'meta_pixel',
                'provider_id' => 'not-a-meta-id',
            ]))
            ->assertSessionHasErrors('provider_id');

        $this->actingAs($owner)
            ->post('/dashboard/pixels', $this->pixelPayload([
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
            ->post('/dashboard/pixels', $this->pixelPayload())
            ->assertSessionHasErrors('plan');

        $this->actingAs($owner)
            ->post('/dashboard/link-pages', $this->pagePayload())
            ->assertRedirect('/dashboard/link-pages/1');

        $this->actingAs($owner)
            ->post('/dashboard/link-pages', $this->pagePayload(['slug' => 'second-campaign']))
            ->assertSessionHasErrors('plan');

        $this->withoutVite();

        $this->actingAs($owner)
            ->get('/account-settings/billing')
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
            ->patch("/dashboard/link-pages/{$page->id}/items/{$second->id}", $this->itemPayload([
                'title' => 'Updated destination',
                'destination_url' => 'https://example.com/updated',
                'is_active' => false,
            ]))
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/items/reorder", [
                'items' => [
                    ['type' => 'link', 'id' => $second->id],
                    ['type' => 'link', 'id' => $first->id],
                ],
            ])
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

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
            ->get("/dashboard/link-pages/{$page->id}")
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->patch("/dashboard/link-pages/{$page->id}", $this->pagePayload())
            ->assertNotFound();

        $foreignPage = $this->createLinkPage($otherUser, $this->pagePayload([
            'slug' => 'other-campaign',
        ]));
        $foreignItem = $this->createItem($otherUser, $foreignPage);

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/items/{$foreignItem->id}", $this->itemPayload())
            ->assertNotFound();
    }

    public function test_link_page_items_reject_non_http_and_first_party_destinations(): void
    {
        config()->set('linkpilot.default_hosts', ['alias.linkpilot.test']);
        $owner = User::factory()->create();
        $page = $this->createLinkPage($owner, $this->pagePayload());
        $claimedHostUrls = [];

        foreach (['pending_dns', 'active', 'failed'] as $index => $status) {
            $domain = $owner->currentWorkspace->customDomains()->create([
                'user_id' => $owner->id,
                'hostname' => "page-customer-{$index}.test",
                'verification_token' => Str::lower(Str::random(40)),
                'status' => $status,
            ]);
            $claimedHostUrls[] = "https://{$domain->hostname}/path";
        }

        foreach (array_merge([
            'ftp://unrelated.example/path',
            'https://alias.linkpilot.test/path',
        ], $claimedHostUrls) as $destination) {
            $this
                ->actingAs($owner)
                ->post("/dashboard/link-pages/{$page->id}/items", $this->itemPayload([
                    'destination_url' => $destination,
                ]))
                ->assertSessionHasErrors('destination_url');
        }

        $this
            ->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/items", $this->itemPayload([
                'destination_url' => 'https://unrelated.example/page',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect("/dashboard/link-pages/{$page->id}");
    }

    public function test_link_page_trigger_rules_allow_five_active_pixels_and_reject_a_sixth(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);
        $page = $this->createLinkPage($owner, $this->pagePayload(['slug' => 'pixel-capacity']));
        $pixels = collect(range(1, 6))->map(fn (int $index): TrackingPixel => $this->createPixel(
            $owner,
            $this->pixelPayload(['name' => "Pixel {$index}", 'provider_id' => "12345678901234{$index}"]),
        ));

        foreach ($pixels->take(5) as $pixel) {
            $this->actingAs($owner)->post("/dashboard/pixels/{$pixel->id}/rules", [
                'target_type' => 'link_page', 'target_id' => $page->id, 'trigger' => 'page_view',
            ])->assertRedirect('/dashboard/pixels');
        }

        $sixth = $pixels->last();
        $this->actingAs($owner)->post("/dashboard/pixels/{$sixth->id}/rules", [
            'target_type' => 'link_page', 'target_id' => $page->id, 'trigger' => 'page_view',
        ])->assertSessionHasErrors();

        $this->assertDatabaseCount('tracking_pixel_rules', 5);
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
        $page = $this->createLinkPage($owner, $this->pagePayload());
        foreach ([$metaPixel, $analyticsPixel] as $assigned) {
            $this->actingAs($owner)->post("/dashboard/pixels/{$assigned->id}/rules", [
                'target_type' => 'link_page', 'target_id' => $page->id, 'trigger' => 'page_view',
            ])->assertRedirect();
        }
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
                ->where('pixels', ['page_view' => [], 'button_click' => [], 'lead_submit' => []])
                ->where('trackingConsent', null),
            )
            ->assertDontSee($metaPixel->provider_id)
            ->assertDontSee('https://example.com/start');

        $this->assertDatabaseCount('analytics_events', 0);

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/pages/launch-campaign')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', [
                    'page_view' => [
                        ['provider' => 'meta_pixel', 'identifier' => '123456789012345'],
                        ['provider' => 'google_analytics', 'identifier' => 'G-AB12CD34'],
                    ],
                    'button_click' => [],
                    'lead_submit' => [],
                ])
            );

        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $owner->id,
            'event_name' => 'link_page.view',
            'link_page_id' => $page->id,
        ]);
    }

    public function test_public_link_page_props_show_the_public_title_but_omit_the_private_name(): void
    {
        $owner = User::factory()->create();
        $page = $this->createLinkPage($owner, $this->pagePayload([
            'name' => 'Internal ops board',
            'title' => 'Public campaign title',
        ]));

        $this->withoutVite();

        $this->get('/pages/launch-campaign')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->component('link-pages/public', false)
                ->where('title', 'Public campaign title')
                // The root `name` slot is the shared app name on every Inertia
                // page; the page-level internal name must not override it.
                ->where('name', config('app.name')),
            )
            ->assertDontSee('Internal ops board');

        // The private management surface keeps showing the internal name.
        $this->assertSame('Internal ops board', $page->fresh()->name);
    }

    public function test_public_link_page_projects_each_meta_pixel_identifier_only_once(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);
        $pixel = $this->createPixel($owner);
        $duplicate = $this->createPixel($owner, $this->pixelPayload([
            'name' => 'Duplicate Meta ID',
            'provider_id' => $pixel->provider_id,
        ]));
        $page = $this->createLinkPage($owner, $this->pagePayload(['slug' => 'duplicate-meta']));
        foreach ([$pixel, $duplicate] as $assigned) {
            $this->actingAs($owner)->post("/dashboard/pixels/{$assigned->id}/rules", [
                'target_type' => 'link_page', 'target_id' => $page->id, 'trigger' => 'page_view',
            ])->assertRedirect();
        }

        $this->withoutVite();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', [
                    'page_view' => [['provider' => 'meta_pixel', 'identifier' => $pixel->provider_id]],
                    'button_click' => [],
                    'lead_submit' => [],
                ])
            );
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
            ->post('/dashboard/pixels', $attributes ?: $this->pixelPayload())
            ->assertRedirect('/dashboard/pixels');

        return TrackingPixel::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLinkPage(User $user, array $attributes = []): LinkPage
    {
        $this->actingAs($user)
            ->post('/dashboard/link-pages', $attributes ?: $this->pagePayload())
            ->assertRedirect();

        return LinkPage::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createItem(User $user, LinkPage $page, array $attributes = []): LinkPageItem
    {
        $this->actingAs($user)
            ->post("/dashboard/link-pages/{$page->id}/items", $attributes ?: $this->itemPayload())
            ->assertRedirect("/dashboard/link-pages/{$page->id}");

        return LinkPageItem::query()
            ->where('link_page_id', $page->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function makePro(User $user): void
    {
        app(WorkspaceUsage::class)->assignPlan($user->currentWorkspace()->firstOrFail(), Plan::query()->where('code', 'pro')->sole());
    }
}
