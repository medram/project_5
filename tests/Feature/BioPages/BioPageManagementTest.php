<?php

namespace Tests\Feature\BioPages;

use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->post('/bio-pages', [
                'handle' => 'Creator-26',
                'display_name' => 'Creator page',
                'bio' => 'A short introduction.',
                'is_published' => false,
            ])
            ->assertRedirect('/bio-pages/1');

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
            ->patch("/bio-pages/{$page->id}", [
                'handle' => 'creator-26',
                'display_name' => 'Creator page',
                'bio' => 'A short introduction.',
                'is_published' => true,
            ])
            ->assertRedirect("/bio-pages/{$page->id}");

        $this->assertDatabaseHas('bio_pages', [
            'id' => $page->id,
            'is_published' => true,
        ]);

        $this
            ->actingAs($owner)
            ->post("/bio-pages/{$page->id}/links", [
                'title' => 'Portfolio',
                'destination_url' => 'https://example.com/portfolio',
                'is_active' => true,
            ])
            ->assertRedirect("/bio-pages/{$page->id}");

        $this
            ->actingAs($owner)
            ->post("/bio-pages/{$page->id}/links", [
                'title' => 'Newsletter',
                'destination_url' => 'https://example.com/newsletter',
                'is_active' => true,
            ])
            ->assertRedirect("/bio-pages/{$page->id}");

        $links = $page->bioLinks()->get();
        $portfolio = $links->firstWhere('title', 'Portfolio');
        $newsletter = $links->firstWhere('title', 'Newsletter');

        $this->assertSame(0, $portfolio->position);
        $this->assertSame(1, $newsletter->position);

        $this
            ->actingAs($owner)
            ->patch("/bio-pages/{$page->id}/links/{$newsletter->id}", [
                'title' => 'Newsletter',
                'destination_url' => 'https://example.com/newsletter',
                'is_active' => false,
            ])
            ->assertRedirect("/bio-pages/{$page->id}");

        $this->assertDatabaseHas('bio_links', [
            'id' => $newsletter->id,
            'is_active' => false,
        ]);

        $this
            ->actingAs($owner)
            ->patch("/bio-pages/{$page->id}/links/reorder", [
                'link_ids' => [$newsletter->id, $portfolio->id],
            ])
            ->assertRedirect("/bio-pages/{$page->id}");

        $this->assertDatabaseHas('bio_links', [
            'id' => $newsletter->id,
            'position' => 0,
        ]);
        $this->assertDatabaseHas('bio_links', [
            'id' => $portfolio->id,
            'position' => 1,
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
            ->get('/bio-pages')
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
            ->get("/bio-pages/{$page->id}")
            ->assertForbidden();

        $this
            ->actingAs($otherUser)
            ->patch("/bio-pages/{$page->id}", [
                'handle' => $page->handle,
                'display_name' => $page->display_name,
                'bio' => $page->bio,
                'is_published' => true,
            ])
            ->assertForbidden();

        $this
            ->actingAs($otherUser)
            ->patch("/bio-pages/{$page->id}/links/reorder", [
                'link_ids' => [$link->id],
            ])
            ->assertForbidden();

        $this
            ->actingAs($otherUser)
            ->delete("/bio-pages/{$page->id}")
            ->assertForbidden();
    }

    public function test_owner_cannot_tamper_with_reorder_ids_from_another_page(): void
    {
        $owner = User::factory()->create();
        $page = BioPage::factory()->for($owner)->create();
        $ownedLink = BioLink::factory()->for($page)->create();
        $foreignLink = BioLink::factory()->create();

        $this
            ->actingAs($owner)
            ->patch("/bio-pages/{$page->id}/links/reorder", [
                'link_ids' => [$foreignLink->id],
            ])
            ->assertSessionHasErrors('link_ids');

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
            ->patch("/bio-pages/{$page->id}/links/{$foreignLink->id}", [
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
            ->post("/bio-pages/{$page->id}/links", [
                'title' => 'Loop',
                'destination_url' => 'HTTP://LOCALHOST:8000/bio/loop',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('destination_url');
    }
}
