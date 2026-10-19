<?php

namespace Tests\Feature\Links;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_future_short_link_returns_not_found_without_recording_a_click(): void
    {
        $owner = User::factory()->create();

        $this
            ->actingAs($owner)
            ->post('/links', [
                'destination_url' => 'https://example.com/future',
                'slug' => 'future-26',
                'is_active' => true,
                'available_from' => now()->addDay()->toDateTimeString(),
            ])
            ->assertRedirect('/links');

        $this->get('/future-26')->assertNotFound();

        $this->assertDatabaseCount('clicks', 0);
    }

    public function test_expired_short_link_returns_not_found_without_recording_a_click(): void
    {
        $owner = User::factory()->create();

        $this
            ->actingAs($owner)
            ->post('/links', [
                'destination_url' => 'https://example.com/expired',
                'slug' => 'expired-26',
                'is_active' => true,
                'expires_at' => now()->subMinute()->toDateTimeString(),
            ])
            ->assertRedirect('/links');

        $this->get('/expired-26')->assertNotFound();

        $this->assertDatabaseCount('clicks', 0);
    }

    public function test_protected_short_link_requires_the_correct_code_before_redirecting(): void
    {
        $owner = User::factory()->create();

        $this
            ->actingAs($owner)
            ->post('/links', [
                'destination_url' => 'https://example.com/private',
                'slug' => 'private-26',
                'is_active' => true,
                'access_code' => 'open-sesame',
            ])
            ->assertRedirect('/links');

        $this->withoutVite();

        $this
            ->get('/private-26')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/unlock', false)
                ->where('slug', 'private-26'),
            );

        $this
            ->post('/private-26/unlock', ['access_code' => 'incorrect'])
            ->assertSessionHasErrors('access_code');

        $this->assertDatabaseCount('clicks', 0);

        $this
            ->post('/private-26/unlock', ['access_code' => 'open-sesame'])
            ->assertRedirect('/private-26');

        $this
            ->get('/private-26')
            ->assertRedirect('https://example.com/private');

        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_dashboard_routes_do_not_shadow_public_short_link_slugs(): void
    {
        Link::factory()->create([
            'slug' => 'folders',
            'destination_url' => 'https://example.com/folders-link',
        ]);
        Link::factory()->create([
            'slug' => 'qr-codes',
            'destination_url' => 'https://example.com/qr-codes-link',
        ]);

        $this->get('/folders')->assertRedirect('https://example.com/folders-link');
        $this->get('/qr-codes')->assertRedirect('https://example.com/qr-codes-link');
    }

    public function test_unknown_short_link_returns_not_found(): void
    {
        $this->get('/missing-26')->assertNotFound();
    }
}
