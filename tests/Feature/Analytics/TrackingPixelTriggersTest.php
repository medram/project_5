<?php

namespace Tests\Feature\Analytics;

use App\Models\BioPage;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrackingPixelTriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_page_and_overlay_attachments_migrate_to_page_view_rules(): void
    {
        $owner = User::factory()->create();
        $pixel = TrackingPixel::factory()->for($owner)->create();
        $linkPage = LinkPage::factory()->for($owner)->create();
        $overlay = LinkOverlay::factory()->for($owner)->create();
        $migrationPath = database_path('migrations/2026_10_23_000600_create_tracking_pixel_rules.php');

        $this->assertFileExists($migrationPath);
        $migration = require $migrationPath;
        $migration->down();

        DB::table('link_page_tracking_pixel')->insert([
            'link_page_id' => $linkPage->id,
            'tracking_pixel_id' => $pixel->id,
        ]);
        DB::table('link_overlay_tracking_pixel')->insert([
            'link_overlay_id' => $overlay->id,
            'tracking_pixel_id' => $pixel->id,
        ]);

        $migration->up();

        $this->assertDatabaseHas('tracking_pixel_rules', [
            'workspace_id' => $owner->currentWorkspace->id,
            'tracking_pixel_id' => $pixel->id,
            'target_type' => 'link_page',
            'target_id' => $linkPage->id,
            'trigger' => 'page_view',
        ]);
        $this->assertDatabaseHas('tracking_pixel_rules', [
            'workspace_id' => $owner->currentWorkspace->id,
            'tracking_pixel_id' => $pixel->id,
            'target_type' => 'link_overlay',
            'target_id' => $overlay->id,
            'trigger' => 'page_view',
        ]);
        $this->assertFalse(Schema::hasTable('link_page_tracking_pixel'));
        $this->assertFalse(Schema::hasTable('link_overlay_tracking_pixel'));
    }

    public function test_owner_can_attach_and_remove_a_trigger_from_an_owned_surface(): void
    {
        $owner = User::factory()->create();
        $pixel = TrackingPixel::factory()->for($owner)->create();
        $page = BioPage::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post("/dashboard/pixels/{$pixel->id}/rules", [
                'target_type' => 'bio_page',
                'target_id' => $page->id,
                'trigger' => 'button_click',
            ])
            ->assertRedirect('/dashboard/pixels');

        $ruleId = DB::table('tracking_pixel_rules')
            ->where('tracking_pixel_id', $pixel->id)
            ->value('id');
        $this->assertDatabaseHas('tracking_pixel_rules', [
            'tracking_pixel_id' => $pixel->id,
            'workspace_id' => $owner->currentWorkspace->id,
            'target_type' => 'bio_page',
            'target_id' => $page->id,
            'trigger' => 'button_click',
        ]);

        $this->actingAs($owner)
            ->delete("/dashboard/pixels/{$pixel->id}/rules/{$ruleId}")
            ->assertRedirect('/dashboard/pixels');

        $this->assertDatabaseMissing('tracking_pixel_rules', [
            'tracking_pixel_id' => $pixel->id,
            'target_type' => 'bio_page',
            'target_id' => $page->id,
            'trigger' => 'button_click',
        ]);
    }

    public function test_rule_management_rejects_inactive_pixels_and_foreign_workspaces(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $activePixel = TrackingPixel::factory()->for($owner)->create();
        $inactivePixel = TrackingPixel::factory()->for($owner)->create(['is_active' => false]);
        $foreignPixel = TrackingPixel::factory()->for($outsider)->create();
        $ownedPage = BioPage::factory()->for($owner)->create();
        $foreignPage = BioPage::factory()->for($outsider)->create();

        $this->actingAs($owner)
            ->post("/dashboard/pixels/{$activePixel->id}/rules", [
                'target_type' => 'bio_page',
                'target_id' => $foreignPage->id,
                'trigger' => 'page_view',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post("/dashboard/pixels/{$inactivePixel->id}/rules", [
                'target_type' => 'bio_page',
                'target_id' => $ownedPage->id,
                'trigger' => 'page_view',
            ])
            ->assertSessionHasErrors('tracking_pixel');

        $this->actingAs($owner)
            ->post("/dashboard/pixels/{$foreignPixel->id}/rules", [
                'target_type' => 'bio_page',
                'target_id' => $ownedPage->id,
                'trigger' => 'page_view',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('tracking_pixel_rules', 0);
    }

    public function test_rule_validation_rejects_unknown_triggers_and_remote_scripts(): void
    {
        $owner = User::factory()->create();
        $pixel = TrackingPixel::factory()->for($owner)->create();
        $page = BioPage::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post("/dashboard/pixels/{$pixel->id}/rules", [
                'target_type' => 'bio_page',
                'target_id' => $page->id,
                'trigger' => 'purchase',
                'remote_script' => 'https://attacker.example/pixel.js',
            ])
            ->assertSessionHasErrors(['trigger', 'remote_script']);

        $this->actingAs($owner)
            ->post("/dashboard/pixels/{$pixel->id}/rules", [
                'target_type' => 'bio_page',
                'target_id' => $page->id,
                'trigger' => 'lead_submit',
            ])
            ->assertSessionHasErrors('trigger');

        $this->assertDatabaseCount('tracking_pixel_rules', 0);
    }

    public function test_public_bio_page_projects_only_valid_consented_page_and_button_rules(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create([
            'handle' => 'pixel-bio',
            'is_published' => true,
        ]);
        $pixel = TrackingPixel::factory()->for($owner)->create([
            'provider' => 'meta_pixel',
            'provider_id' => '123456789012345',
        ]);
        $malformedPixel = TrackingPixel::factory()->for($owner)->create([
            'provider' => 'meta_pixel',
            'provider_id' => '<script>bad</script>',
        ]);

        $this->addRule($pixel, 'bio_page', $page->id, 'page_view');
        $this->addRule($pixel, 'bio_page', $page->id, 'button_click');
        $this->addRule($malformedPixel, 'bio_page', $page->id, 'page_view');
        $this->withoutVite();

        $this->get('/bio/pixel-bio')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', $this->emptyPixelGroups())
                ->where('trackingConsent', null),
            );

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/bio/pixel-bio')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', [
                    'page_view' => [['provider' => 'meta_pixel', 'identifier' => '123456789012345']],
                    'button_click' => [['provider' => 'meta_pixel', 'identifier' => '123456789012345']],
                    'lead_submit' => [],
                ])
                ->where('trackingConsent', 'granted'),
            )
            ->assertDontSee('<script>bad</script>');
    }

    public function test_public_link_page_projects_only_its_consent_scoped_trigger_groups(): void
    {
        $owner = User::factory()->create();
        $page = LinkPage::factory()->for($owner)->create([
            'slug' => 'pixel-page',
            'is_published' => true,
        ]);
        $otherPage = LinkPage::factory()->for($owner)->create([
            'slug' => 'other-pixel-page',
            'is_published' => true,
        ]);
        $pixel = TrackingPixel::factory()->for($owner)->create([
            'provider' => 'google_analytics',
            'provider_id' => 'G-AB12CD34',
        ]);

        $this->addRule($pixel, 'link_page', $page->id, 'page_view');
        $this->addRule($pixel, 'link_page', $page->id, 'button_click');
        $this->addRule($pixel, 'link_page', $page->id, 'lead_submit');
        $this->addRule($pixel, 'link_page', $otherPage->id, 'button_click');
        $this->withoutVite();

        $this->get('/pages/pixel-page')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', $this->emptyPixelGroups()),
            );

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/pages/pixel-page')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', [
                    'page_view' => [['provider' => 'google_analytics', 'identifier' => 'G-AB12CD34']],
                    'button_click' => [['provider' => 'google_analytics', 'identifier' => 'G-AB12CD34']],
                    'lead_submit' => [['provider' => 'google_analytics', 'identifier' => 'G-AB12CD34']],
                ]),
            );
    }

    public function test_public_overlay_projects_only_its_consent_scoped_triggers(): void
    {
        $owner = User::factory()->create();
        $overlay = LinkOverlay::factory()->for($owner)->create();
        $pixel = TrackingPixel::factory()->for($owner)->create();
        $link = Link::factory()->for($owner)->create([
            'slug' => 'pixel-overlay',
            'delivery_mode' => 'overlay',
            'link_overlay_id' => $overlay->id,
        ]);

        $this->addRule($pixel, 'link_overlay', $overlay->id, 'page_view');
        $this->addRule($pixel, 'link_overlay', $overlay->id, 'button_click');
        $this->withoutVite();

        $this->get('/pixel-overlay')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', $this->emptyPixelGroups()),
            );

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/pixel-overlay')
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('pixels', [
                    'page_view' => [['provider' => 'meta_pixel', 'identifier' => $pixel->provider_id]],
                    'button_click' => [['provider' => 'meta_pixel', 'identifier' => $pixel->provider_id]],
                    'lead_submit' => [],
                ]),
            );

        $this->assertDatabaseCount('clicks', 0);
    }

    /** @param 'bio_page'|'link_page'|'link_overlay' $targetType */
    private function addRule(TrackingPixel $pixel, string $targetType, int $targetId, string $trigger): void
    {
        DB::table('tracking_pixel_rules')->insert([
            'workspace_id' => $pixel->workspace_id,
            'tracking_pixel_id' => $pixel->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'trigger' => $trigger,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{page_view: list<never>, button_click: list<never>, lead_submit: list<never>} */
    private function emptyPixelGroups(): array
    {
        return [
            'page_view' => [],
            'button_click' => [],
            'lead_submit' => [],
        ];
    }
}
