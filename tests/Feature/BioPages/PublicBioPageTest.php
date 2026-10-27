<?php

namespace Tests\Feature\BioPages;

use App\Models\BioLink;
use App\Models\BioPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicBioPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_bio_page_shows_only_active_links_in_position_order(): void
    {
        $page = BioPage::factory()->create([
            'handle' => 'creator-26',
            'display_name' => 'Creator Name',
            'bio' => 'Links and notes from a creator.',
            'is_published' => true,
        ]);

        BioLink::factory()->for($page)->create([
            'title' => 'Later',
            'position' => 2,
            'is_active' => true,
        ]);
        BioLink::factory()->for($page)->create([
            'title' => 'Hidden',
            'position' => 1,
            'is_active' => false,
        ]);
        BioLink::factory()->for($page)->create([
            'title' => 'First',
            'position' => 0,
            'is_active' => true,
        ]);

        $this->withoutVite();

        $this
            ->get('/bio/creator-26')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('bio-pages/public', false)
                ->where('presentation.displayName', 'Creator Name')
                ->where('presentation.bio', 'Links and notes from a creator.')
                ->where('presentation.handle', 'creator-26')
                ->has('presentation.items', 2)
                ->where('presentation.items.0.type', 'link')
                ->where('presentation.items.0.title', 'First')
                ->where('presentation.items.1.title', 'Later'),
            );
    }

    public function test_unpublished_bio_page_returns_not_found(): void
    {
        BioPage::factory()->create([
            'handle' => 'private-26',
            'is_published' => false,
        ]);

        $this->get('/bio/private-26')->assertNotFound();
    }

    public function test_unknown_bio_page_returns_not_found(): void
    {
        $this->get('/bio/missing-26')->assertNotFound();
    }
}
