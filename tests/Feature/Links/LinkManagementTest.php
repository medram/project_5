<?php

namespace Tests\Feature\Links;

use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_normalized_custom_link(): void
    {
        $response = $this
            ->actingAs(User::factory()->create())
            ->post('/links', [
                'destination_url' => 'https://example.com/launch',
                'title' => 'Launch',
                'slug' => 'Launch-26',
                'is_active' => true,
            ]);

        $response->assertRedirect('/links');

        $this->assertDatabaseHas('links', [
            'slug' => 'launch-26',
            'destination_url' => 'https://example.com/launch',
            'is_active' => true,
        ]);
    }

    public function test_owner_gets_a_generated_seven_character_slug_when_custom_slug_is_omitted(): void
    {
        $response = $this
            ->actingAs(User::factory()->create())
            ->post('/links', [
                'destination_url' => 'https://example.com/generated',
                'is_active' => true,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/links');

        $this->assertMatchesRegularExpression('/^[a-z0-9]{7}$/', Link::query()->sole()->slug);
    }

    public function test_generated_slug_retries_after_a_collision(): void
    {
        $owner = User::factory()->create();
        $this->createLink($owner, ['slug' => 'taken123']);

        Str::createRandomStringsUsingSequence(['taken123', 'fresh456']);

        try {
            $link = $owner->links()->create([
                'destination_url' => 'https://example.com/retry',
                'is_active' => true,
            ]);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame('fresh456', $link->slug);
    }

    public function test_invalid_custom_slug_is_rejected(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->post('/links', [
                'destination_url' => 'https://example.com/invalid-slug',
                'slug' => 'invalid slug',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_custom_slug_must_be_unique_after_normalization(): void
    {
        $owner = User::factory()->create();
        $this->createLink($owner, ['slug' => 'taken-26']);

        $this
            ->actingAs($owner)
            ->post('/links', [
                'destination_url' => 'https://example.com/duplicate',
                'slug' => 'TAKEN-26',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->post('/links', [
                'destination_url' => 'https://example.com/reserved',
                'slug' => 'dashboard',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_non_http_destination_is_rejected(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->post('/links', [
                'destination_url' => 'ftp://example.com/file',
                'slug' => 'ftp-link',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('destination_url');
    }

    public function test_destination_at_the_application_origin_is_rejected(): void
    {
        config()->set('app.url', 'http://localhost:8000');

        $this
            ->actingAs(User::factory()->create())
            ->post('/links', [
                'destination_url' => 'HTTP://LOCALHOST:8000/loop',
                'slug' => 'loop-link',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('destination_url');
    }

    public function test_owner_can_update_and_deactivate_a_link(): void
    {
        $owner = User::factory()->create();
        $link = $this->createLink($owner);

        $this
            ->actingAs($owner)
            ->patch("/links/{$link->id}", [
                'destination_url' => 'https://example.com/updated',
                'title' => 'Updated',
                'slug' => 'updated-link',
                'is_active' => false,
            ])
            ->assertRedirect('/links');

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'destination_url' => 'https://example.com/updated',
            'title' => 'Updated',
            'slug' => 'updated-link',
            'is_active' => false,
        ]);
    }

    public function test_owner_can_delete_a_link(): void
    {
        $owner = User::factory()->create();
        $link = $this->createLink($owner);

        $this
            ->actingAs($owner)
            ->delete("/links/{$link->id}")
            ->assertRedirect('/links');

        $this->assertDatabaseMissing('links', ['id' => $link->id]);
    }

    public function test_other_user_cannot_update_a_link(): void
    {
        $link = $this->createLink(User::factory()->create());

        $this
            ->actingAs(User::factory()->create())
            ->patch("/links/{$link->id}", [
                'destination_url' => 'https://example.com/takeover',
                'slug' => 'takeover',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_other_user_cannot_delete_a_link(): void
    {
        $link = $this->createLink(User::factory()->create());

        $this
            ->actingAs(User::factory()->create())
            ->delete("/links/{$link->id}")
            ->assertForbidden();
    }

    public function test_index_only_includes_the_signed_in_owners_links(): void
    {
        $owner = User::factory()->create();
        $ownerLink = $this->createLink($owner, ['slug' => 'owner-26']);
        Click::factory()->count(2)->for($ownerLink)->create();
        $this->createLink(User::factory()->create(), ['slug' => 'other-26']);

        $this->withoutVite();

        $this
            ->actingAs($owner)
            ->get('/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/index', false)
                ->has('links', 1)
                ->where('links.0.slug', 'owner-26')
                ->where('links.0.is_active', true)
                ->where('links.0.clicks_count', 2),
            );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLink(User $owner, array $attributes = []): Link
    {
        return $owner->links()->create(array_merge([
            'destination_url' => 'https://example.com/original',
            'title' => 'Original',
            'slug' => 'original-link',
            'is_active' => true,
        ], $attributes));
    }
}
