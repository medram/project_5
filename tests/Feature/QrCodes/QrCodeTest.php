<?php

namespace Tests\Feature\QrCodes;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_includes_the_signed_in_owners_links(): void
    {
        $owner = User::factory()->create();
        $ownedLink = Link::factory()->for($owner)->create(['slug' => 'owned-qr-26']);
        Link::factory()->create(['slug' => 'other-qr-26']);

        $this->withoutVite();

        $this
            ->actingAs($owner)
            ->get('/qr-codes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('qr-codes/index', false)
                ->has('links', 1)
                ->where('links.0.id', $ownedLink->id)
                ->where('links.0.slug', 'owned-qr-26'),
            );
    }

    public function test_owner_can_render_an_svg_qr_code_for_a_link(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create(['slug' => 'svg-qr-26']);

        $this
            ->actingAs($owner)
            ->get("/links/{$link->id}/qr-code.svg")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);
    }

    public function test_non_owner_cannot_render_a_links_qr_code(): void
    {
        $link = Link::factory()->create(['slug' => 'private-qr-26']);

        $this
            ->actingAs(User::factory()->create())
            ->get("/links/{$link->id}/qr-code.svg")
            ->assertForbidden();
    }
}
