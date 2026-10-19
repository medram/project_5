<?php

namespace Tests\Feature\Links;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_short_link_records_one_click_then_redirects(): void
    {
        $link = Link::factory()->create([
            'slug' => 'launch-26',
            'destination_url' => 'https://example.com/launch',
        ]);

        $this
            ->get('/launch-26')
            ->assertRedirect('https://example.com/launch');

        $this->assertDatabaseCount('clicks', 1);
        $this->assertDatabaseHas('clicks', ['link_id' => $link->id]);
    }

    public function test_inactive_short_link_returns_not_found_without_recording_a_click(): void
    {
        Link::factory()->create([
            'slug' => 'paused-26',
            'is_active' => false,
        ]);

        $this
            ->get('/paused-26')
            ->assertNotFound();

        $this->assertDatabaseCount('clicks', 0);
    }

    public function test_unknown_short_link_returns_not_found(): void
    {
        $this->get('/missing-26')->assertNotFound();
    }
}
