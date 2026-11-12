<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\LinkPageItem;
use App\Models\Plan;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use App\Services\Billing\WorkspaceUsage;
use App\Services\LinkPages\LinkPageVariantAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class LinkPageVariantsTest extends TestCase
{
    use RefreshDatabase;

    private const VARIANT_COOKIE = 'linkpilot_link_page_variant';

    private const MEDIA_DISK = 'link-page-variants-test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.linkpilot.analytics.privacy_url', 'https://linkpilot.test/privacy');
        config()->set('services.linkpilot.media.disk', self::MEDIA_DISK);
        config()->set('filesystems.disks.'.self::MEDIA_DISK, [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/'.self::MEDIA_DISK),
            'throw' => false,
        ]);
        Storage::fake(self::MEDIA_DISK);
    }

    public function test_new_link_pages_have_exactly_one_control_variant(): void
    {
        $page = $this->createPage(User::factory()->create());
        $control = DB::table('link_page_variants')->where('link_page_id', $page->id)->sole();

        $this->assertSame('Control', $control->name);
        $this->assertSame(100, (int) $control->weight);
        $this->assertSame(1, (int) $control->is_control);
        $this->assertSame(1, (int) $control->is_active);
        $this->assertSame(1, DB::table('link_page_variants')->where('link_page_id', $page->id)->where('is_control', true)->count());
    }

    public function test_variant_weights_stay_at_one_hundred_and_clients_cannot_create_another_control(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $controlId = $this->controlVariantId($page);
        $variantId = $this->createVariant($owner, $page, 'Campaign', 40);

        $this->assertDatabaseHas('link_page_variants', ['id' => $controlId, 'weight' => 60]);

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/variants/{$variantId}", [
                'name' => 'Campaign',
                'weight' => 101,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('weight');

        $this->assertDatabaseHas('link_page_variants', ['id' => $controlId, 'weight' => 60]);
        $this->assertDatabaseHas('link_page_variants', ['id' => $variantId, 'weight' => 40]);

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/variants", [
                'name' => 'Forged control',
                'weight' => 10,
                'is_control' => true,
            ])
            ->assertSessionHasErrors('is_control');

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/variants", [
                'name' => 'Over allocation',
                'weight' => 61,
            ])
            ->assertSessionHasErrors('weight');

        $this->assertSame(2, DB::table('link_page_variants')->where('link_page_id', $page->id)->count());
        $this->assertSame(100, (int) DB::table('link_page_variants')->where('link_page_id', $page->id)->where('is_active', true)->sum('weight'));
    }

    public function test_editor_and_item_block_mutations_are_scoped_to_the_selected_variant_and_page(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $controlId = $this->controlVariantId($page);
        $variantId = $this->createVariant($owner, $page, 'Campaign', 40);
        $controlItem = $this->createItem($owner, $page, $controlId, 'Control destination');
        $variantItem = $this->createItem($owner, $page, $variantId, 'Campaign destination');
        $variantBlock = $this->createTextBlock($owner, $page, $variantId, 'Campaign-only copy');

        $this->actingAs($owner)
            ->get("/dashboard/link-pages/{$page->id}?variant={$variantId}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('selectedVariantId', $variantId)
                ->has('variants', 2)
                ->has('items', 2)
                ->where('items.0.id', $variantItem->id)
                ->where('items.1.id', $variantBlock->id)
                ->missing('items.2')
                ->has('previewItems', 2));

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/items/{$variantItem->id}", [
                'title' => 'Moved destination',
                'destination_url' => 'https://example.com/moved',
                'is_active' => true,
                'link_page_variant_id' => $controlId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('link_page_items', [
            'id' => $variantItem->id,
            'link_page_variant_id' => $controlId,
        ]);
        $this->assertDatabaseHas('link_page_items', [
            'id' => $controlItem->id,
            'link_page_variant_id' => $controlId,
        ]);

        $otherOwner = User::factory()->create();
        $otherPage = $this->createPage($otherOwner);
        $foreignVariantId = $this->controlVariantId($otherPage);

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/items", [
                'title' => 'Invalid assignment',
                'destination_url' => 'https://example.com/invalid',
                'is_active' => true,
                'link_page_variant_id' => $foreignVariantId,
            ])
            ->assertSessionHasErrors('link_page_variant_id');

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'text',
                'text' => 'Invalid assignment',
                'is_active' => true,
                'link_page_variant_id' => $foreignVariantId,
            ])
            ->assertSessionHasErrors('link_page_variant_id');

        $this->assertDatabaseHas('link_page_blocks', [
            'id' => $variantBlock->id,
            'link_page_variant_id' => $variantId,
        ]);
    }

    public function test_public_assignment_is_weighted_encrypted_and_stable_for_thirty_days(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $controlId = $this->controlVariantId($page);
        $variantId = $this->createVariant($owner, $page, 'Campaign', 100);
        $controlBlock = $this->createTextBlock($owner, $page, $controlId, 'Control content');
        $campaignBlock = $this->createTextBlock($owner, $page, $variantId, 'Campaign content');
        $this->assertDatabaseHas('link_page_variants', ['id' => $controlId, 'weight' => 0]);
        $this->assertDatabaseHas('link_page_variants', ['id' => $variantId, 'weight' => 100]);
        $this->assertDatabaseHas('link_page_blocks', ['id' => $campaignBlock->id, 'link_page_variant_id' => $variantId]);
        $this->assertNotSame($controlBlock->id, $campaignBlock->id);
        $this->assertSame(
            $variantId,
            app(LinkPageVariantAssignment::class)
                ->forPageView(Request::create("/pages/{$page->slug}"), $page)
                ->id,
        );
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));

        $first = $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->has('items', 1)
                ->where('items.0.text', 'Campaign content'));
        $cookie = $this->variantCookie($first);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
        $this->assertSame(now('UTC')->addDays(30)->getTimestamp(), $cookie->getExpiresTime());
        $this->assertNotSame((string) $variantId, $cookie->getValue());

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->has('items', 1)
                ->where('items.0.text', 'Campaign content'));

        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_form_and_media_actions_reject_content_from_a_different_variant(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $controlId = $this->controlVariantId($page);
        $variantId = $this->createVariant($owner, $page, 'Campaign', 100);
        $form = $this->createFormBlock($owner, $page, $variantId);
        $image = $this->createImageBlock($owner, $page, $variantId);
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));

        $this->withCookie(self::VARIANT_COOKIE, (string) $controlId)
            ->post("/pages/{$page->slug}/blocks/{$form->id}/leads", [
                'lead_consent' => true,
                'values' => ['name' => 'Ada'],
            ])
            ->assertNotFound();

        $this->withCookie(self::VARIANT_COOKIE, (string) $controlId)
            ->get('/content-media/'.$image->contentMedia()->value('public_id'))
            ->assertNotFound();

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->post("/pages/{$page->slug}/blocks/{$form->id}/leads", [
                'lead_consent' => true,
                'values' => ['name' => 'Ada'],
            ])
            ->assertRedirect("/pages/{$page->slug}");

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->get('/content-media/'.$image->contentMedia()->value('public_id'))
            ->assertOk();

        $this->assertDatabaseHas('link_page_leads', [
            'link_page_block_id' => $form->id,
            'link_page_variant_id' => $variantId,
        ]);
        $this->assertDatabaseHas('link_page_blocks', [
            'id' => $form->id,
            'link_page_variant_id' => $variantId,
        ]);
        $this->assertDatabaseHas('link_page_blocks', [
            'id' => $image->id,
            'link_page_variant_id' => $variantId,
        ]);
        $this->assertDatabaseHas('link_page_variants', ['id' => $controlId, 'is_control' => true]);
    }

    public function test_inactive_deleted_and_cross_page_cookie_assignments_fall_back_to_control(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $controlId = $this->controlVariantId($page);
        $variantId = $this->createVariant($owner, $page, 'Campaign', 100);
        $this->createTextBlock($owner, $page, $controlId, 'Control content');
        $this->createTextBlock($owner, $page, $variantId, 'Campaign content');
        $this->get("/pages/{$page->slug}")->assertOk();

        $otherPage = $this->createPage($owner);
        $otherControlId = $this->controlVariantId($otherPage);
        $otherVariantId = $this->createVariant($owner, $otherPage, 'Other campaign', 100);
        $this->createTextBlock($owner, $otherPage, $otherControlId, 'Other control');
        $this->createTextBlock($owner, $otherPage, $otherVariantId, 'Other campaign content');

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->get("/pages/{$otherPage->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->has('items', 1)
                ->where('items.0.text', 'Other control'));

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/variants/{$variantId}", [
                'name' => 'Campaign',
                'weight' => 0,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->has('items', 1)
                ->where('items.0.text', 'Control content'));

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/variants/{$variantId}", [
                'name' => 'Campaign',
                'weight' => 100,
                'is_active' => true,
            ])
            ->assertRedirect();
        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('items.0.text', 'Campaign content'));
        $this->actingAs($owner)
            ->delete("/dashboard/link-pages/{$page->id}/variants/{$variantId}")
            ->assertRedirect();

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->has('items', 2)
                ->where('items.0.text', 'Control content')
                ->where('items.1.text', 'Campaign content'));

        $this->assertTrue(DB::table('link_page_blocks')
            ->where('id', $page->blocks()->where('content->text', 'Campaign content')->value('id'))
            ->where('link_page_variant_id', $controlId)
            ->exists());
        $this->assertTrue(DB::table('link_page_variants')->where('id', $variantId)->whereNotNull('deleted_at')->exists());
    }

    public function test_variant_attribution_and_lead_conversion_events_require_analytics_consent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $variantId = $this->createVariant($owner, $page, 'Campaign', 100);
        $item = $this->createItem($owner, $page, $variantId, 'Campaign button');
        $form = $this->createFormBlock($owner, $page, $variantId);

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/pages/{$page->slug}")
            ->assertOk();

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link_page.view',
            'link_page_id' => $page->id,
            'link_page_variant_id' => $variantId,
        ]);

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/pages/{$page->slug}/items/{$item->id}")
            ->assertRedirect('https://example.com/campaign');

        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link_page.clicked',
            'link_page_id' => $page->id,
            'link_page_variant_id' => $variantId,
        ]);

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->post("/pages/{$page->slug}/blocks/{$form->id}/leads", [
                'lead_consent' => true,
                'values' => ['name' => 'Ada'],
            ])
            ->assertRedirect("/pages/{$page->slug}");

        $this->assertDatabaseHas('link_page_leads', [
            'link_page_block_id' => $form->id,
            'link_page_variant_id' => $variantId,
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link_page.lead_submitted',
            'link_page_id' => $page->id,
            'link_page_variant_id' => $variantId,
        ]);

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::DENIED)
            ->post("/pages/{$page->slug}/blocks/{$form->id}/leads", [
                'lead_consent' => true,
                'values' => ['name' => 'Grace'],
            ])
            ->assertRedirect("/pages/{$page->slug}");

        $this->assertSame(1, DB::table('link_page_leads')
            ->where('link_page_block_id', $form->id)
            ->whereNull('link_page_variant_id')
            ->count());
        $this->assertSame(3, DB::table('analytics_events')->where('link_page_id', $page->id)->count());
    }

    public function test_variant_insights_aggregate_views_clicks_and_leads_without_identity_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $variantId = $this->createVariant($owner, $page, 'Campaign', 100);
        $item = $this->createItem($owner, $page, $variantId, 'Campaign button');
        $form = $this->createFormBlock($owner, $page, $variantId);
        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/pages/{$page->slug}")
            ->assertOk();

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/pages/{$page->slug}/items/{$item->id}")
            ->assertRedirect();

        $this->withCookie(self::VARIANT_COOKIE, (string) $variantId)
            ->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->post("/pages/{$page->slug}/blocks/{$form->id}/leads", [
                'lead_consent' => true,
                'values' => ['name' => 'Ada'],
            ])
            ->assertRedirect();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::DENIED)
            ->get("/pages/{$page->slug}")
            ->assertOk();

        $this->actingAs($owner)
            ->get("/dashboard/link-pages/{$page->id}/insights?days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->has('insights.variants', 2)
                ->where('insights.days', 7)
                ->where('insights.variants.0.isControl', true)
                ->where('insights.variants.0.views', 0)
                ->where('insights.variants.1.id', $variantId)
                ->where('insights.variants.1.views', 1)
                ->where('insights.variants.1.clicks', 1)
                ->where('insights.variants.1.leads', 1)
                ->missing('insights.visitors')
                ->missing('insights.identifiers'));
    }

    private function createPage(User $owner, bool $published = true): LinkPage
    {
        $this->makePro($owner);
        $slug = 'variant-'.Str::lower(Str::random(10));

        $this->actingAs($owner)
            ->post('/dashboard/link-pages', [
                'name' => 'Variant test page',
                'slug' => $slug,
                'title' => 'Variant test page',
                'description' => 'Testing variant behavior.',
                'template' => 'standard',
                'is_published' => $published,
            ])
            ->assertRedirect();

        return LinkPage::query()->where('slug', $slug)->sole();
    }

    private function controlVariantId(LinkPage $page): int
    {
        return (int) DB::table('link_page_variants')
            ->where('link_page_id', $page->id)
            ->where('is_control', true)
            ->value('id');
    }

    private function createVariant(User $owner, LinkPage $page, string $name, int $weight): int
    {
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/variants", [
                'name' => $name,
                'weight' => $weight,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return (int) DB::table('link_page_variants')
            ->where('link_page_id', $page->id)
            ->where('name', $name)
            ->value('id');
    }

    private function createItem(User $owner, LinkPage $page, int $variantId, string $title): LinkPageItem
    {
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/items", [
                'title' => $title,
                'destination_url' => 'https://example.com/campaign',
                'is_active' => true,
                'link_page_variant_id' => $variantId,
            ])
            ->assertRedirect();

        return LinkPageItem::query()->where('link_page_id', $page->id)->where('title', $title)->sole();
    }

    private function createTextBlock(User $owner, LinkPage $page, int $variantId, string $text): LinkPageBlock
    {
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'text',
                'text' => $text,
                'is_active' => true,
                'link_page_variant_id' => $variantId,
            ])
            ->assertRedirect();

        return LinkPageBlock::query()
            ->where('link_page_id', $page->id)
            ->where('type', 'text')
            ->where('content->text', $text)
            ->sole();
    }

    private function createFormBlock(User $owner, LinkPage $page, int $variantId): LinkPageBlock
    {
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'form',
                'fields' => [[
                    'id' => 'name',
                    'label' => 'Name',
                    'type' => 'text',
                    'required' => true,
                ]],
                'is_active' => true,
                'link_page_variant_id' => $variantId,
            ])
            ->assertRedirect();

        return LinkPageBlock::query()->where('link_page_id', $page->id)->where('type', 'form')->sole();
    }

    private function createImageBlock(User $owner, LinkPage $page, int $variantId): LinkPageBlock
    {
        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'image',
                'alt_text' => 'Campaign artwork',
                'image' => UploadedFile::fake()->image('campaign.png', 2, 2),
                'is_active' => true,
                'link_page_variant_id' => $variantId,
            ])
            ->assertRedirect();

        return LinkPageBlock::query()->where('link_page_id', $page->id)->where('type', 'image')->sole();
    }

    private function variantCookie(TestResponse $response): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === self::VARIANT_COOKIE) {
                return $cookie;
            }
        }

        $this->fail('The public link-page response did not set its variant cookie.');
    }

    private function makePro(User $user): void
    {
        app(WorkspaceUsage::class)->assignPlan($user->currentWorkspace()->firstOrFail(), Plan::query()->where('code', 'pro')->sole());
    }
}
