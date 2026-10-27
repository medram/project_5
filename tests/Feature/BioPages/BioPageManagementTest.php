<?php

namespace Tests\Feature\BioPages;

use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BioPageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_normalized_bio_page_and_manage_its_links(): void
    {
        $owner = User::factory()->create();

        $this
            ->actingAs($owner)
            ->post('/dashboard/biolinks', [
                'handle' => 'Creator-26',
                'display_name' => 'Creator page',
                'bio' => 'A short introduction.',
                'is_published' => false,
            ])
            ->assertRedirect('/dashboard/biolinks/1');

        $page = BioPage::query()->sole();

        $this->assertDatabaseHas('bio_pages', [
            'id' => $page->id,
            'user_id' => $owner->id,
            'handle' => 'creator-26',
            'display_name' => 'Creator page',
            'is_published' => false,
        ]);

        $this
            ->actingAs($owner)
            ->patch("/dashboard/biolinks/{$page->id}", [
                'handle' => 'creator-26',
                'display_name' => 'Creator page',
                'bio' => 'A short introduction.',
                'is_published' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $this->assertDatabaseHas('bio_pages', [
            'id' => $page->id,
            'is_published' => true,
        ]);

        $this
            ->actingAs($owner)
            ->post("/dashboard/biolinks/{$page->id}/links", [
                'title' => 'Portfolio',
                'destination_url' => 'https://example.com/portfolio',
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $this
            ->actingAs($owner)
            ->post("/dashboard/biolinks/{$page->id}/links", [
                'title' => 'Newsletter',
                'destination_url' => 'https://example.com/newsletter',
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $links = $page->bioLinks()->get();
        $portfolio = $links->firstWhere('title', 'Portfolio');
        $newsletter = $links->firstWhere('title', 'Newsletter');

        $this->assertSame(0, $portfolio->position);
        $this->assertSame(1, $newsletter->position);

        $this
            ->actingAs($owner)
            ->patch("/dashboard/biolinks/{$page->id}/links/{$newsletter->id}", [
                'title' => 'Newsletter',
                'destination_url' => 'https://example.com/newsletter',
                'is_active' => false,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $this->assertDatabaseHas('bio_links', [
            'id' => $newsletter->id,
            'is_active' => false,
        ]);

        $this
            ->actingAs($owner)
            ->patch("/dashboard/biolinks/{$page->id}/content/reorder", [
                'items' => [
                    ['type' => 'link', 'id' => $newsletter->id],
                    ['type' => 'link', 'id' => $portfolio->id],
                ],
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $this->assertDatabaseHas('bio_links', [
            'id' => $newsletter->id,
            'position' => 0,
        ]);
        $this->assertDatabaseHas('bio_links', [
            'id' => $portfolio->id,
            'position' => 1,
        ]);
    }

    public function test_owner_can_toggle_a_link_with_a_partial_update(): void
    {
        $page = BioPage::factory()->create();
        $link = BioLink::factory()->for($page)->create(['is_active' => true]);

        $this->actingAs($page->user)
            ->patch("/dashboard/biolinks/{$page->id}/links/{$link->id}", [
                'is_active' => false,
            ])
            ->assertRedirect("/dashboard/biolinks/{$page->id}");

        $this->assertDatabaseHas('bio_links', [
            'id' => $link->id,
            'is_active' => false,
        ]);
    }

    public function test_index_includes_only_the_signed_in_owners_bio_pages(): void
    {
        $owner = User::factory()->create();
        BioPage::factory()->for($owner)->create(['handle' => 'owner-creator']);
        BioPage::factory()->create(['handle' => 'other-creator']);

        $this->withoutVite();

        $this
            ->actingAs($owner)
            ->get('/dashboard/biolinks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bio-pages/index', false)
                ->has('bioPages', 1)
                ->where('bioPages.0.handle', 'owner-creator'),
            );
    }

    public function test_other_users_cannot_update_delete_or_reorder_a_bio_page(): void
    {
        $page = BioPage::factory()->create();
        $link = BioLink::factory()->for($page)->create();
        $otherUser = User::factory()->create();

        $this
            ->actingAs($otherUser)
            ->get("/dashboard/biolinks/{$page->id}")
            ->assertNotFound();

        $this
            ->actingAs($otherUser)
            ->patch("/dashboard/biolinks/{$page->id}", [
                'handle' => $page->handle,
                'display_name' => $page->display_name,
                'bio' => $page->bio,
                'is_published' => true,
            ])
            ->assertNotFound();

        $this
            ->actingAs($otherUser)
            ->patch("/dashboard/biolinks/{$page->id}/content/reorder", [
                'items' => [['type' => 'link', 'id' => $link->id]],
            ])
            ->assertNotFound();

        $this
            ->actingAs($otherUser)
            ->delete("/dashboard/biolinks/{$page->id}")
            ->assertNotFound();
    }

    public function test_owner_cannot_tamper_with_reorder_ids_from_another_page(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create();
        $ownedLink = BioLink::factory()->for($page)->create();
        $foreignLink = BioLink::factory()->create();

        $this
            ->actingAs($owner)
            ->patch("/dashboard/biolinks/{$page->id}/content/reorder", [
                'items' => [['type' => 'link', 'id' => $foreignLink->id]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseHas('bio_links', [
            'id' => $ownedLink->id,
            'position' => 0,
        ]);
    }

    public function test_owner_cannot_update_a_link_outside_the_parent_page(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create();
        $foreignLink = BioLink::factory()->create();

        $this
            ->actingAs($owner)
            ->patch("/dashboard/biolinks/{$page->id}/links/{$foreignLink->id}", [
                'title' => 'Tampered',
                'destination_url' => 'https://example.com/tampered',
                'is_active' => true,
            ])
            ->assertNotFound();
    }

    public function test_owner_cannot_create_a_self_referential_bio_link(): void
    {
        config()->set('app.url', 'http://localhost:8000');

        $page = BioPage::factory()->create();

        $this
            ->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/links", [
                'title' => 'Loop',
                'destination_url' => 'HTTP://LOCALHOST:8000/bio/loop',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('destination_url');
    }

    public function test_bio_links_reject_alias_and_claimed_custom_domain_destinations(): void
    {
        config()->set('linkpilot.default_hosts', ['alias.linkpilot.test']);
        $page = BioPage::factory()->create();
        $domain = $page->user->currentWorkspace->customDomains()->create([
            'user_id' => $page->user_id,
            'hostname' => 'bio-customer.test',
            'verification_token' => Str::lower(Str::random(40)),
            'status' => 'failed',
        ]);

        foreach (['https://alias.linkpilot.test/path', "https://{$domain->hostname}/path"] as $destination) {
            $this
                ->actingAs($page->user)
                ->post("/dashboard/biolinks/{$page->id}/links", [
                    'title' => 'Loop',
                    'destination_url' => $destination,
                    'is_active' => true,
                ])
                ->assertSessionHasErrors('destination_url');
        }

        $this
            ->actingAs($page->user)
            ->post("/dashboard/biolinks/{$page->id}/links", [
                'title' => 'Allowed',
                'destination_url' => 'https://unrelated.example/bio',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();
    }
}
