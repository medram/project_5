<?php

namespace Tests\Feature\Links;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tag_is_created_in_the_active_workspace_with_a_unique_slug(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->linkTags()->create(['name' => 'Product launch', 'slug' => 'product-launch']);

        $this->actingAs($owner)->post('/dashboard/link-tags', [
            'name' => 'Product launch',
            'color' => '#2563EB',
        ])->assertRedirect('/dashboard/links');

        $this->assertDatabaseHas('link_tags', [
            'workspace_id' => $workspace->id,
            'name' => 'Product launch',
            'slug' => 'product-launch-2',
            'color' => '#2563EB',
        ]);

        $this->actingAs($owner)->post('/dashboard/link-tags', [
            'name' => 'Unsafe',
            'color' => 'red; background: url(https://evil.example)',
        ])->assertSessionHasErrors('color');

        $this->assertDatabaseMissing('link_tags', [
            'workspace_id' => $workspace->id,
            'name' => 'Unsafe',
        ]);
    }

    public function test_omitted_tag_ids_preserve_tags_but_an_empty_list_removes_them(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $tag = $workspace->linkTags()->create([
            'name' => 'Launch',
            'slug' => 'launch',
        ]);
        $link = $workspace->links()->create([
            'user_id' => $owner->id,
            'destination_url' => 'https://example.com/tag-edit',
            'slug' => 'tag-edit',
            'is_active' => true,
        ]);
        $link->tags()->attach($tag);
        $payload = [
            'destination_url' => $link->destination_url,
            'slug' => $link->slug,
            'is_active' => true,
        ];

        $this->actingAs($owner)->patch("/dashboard/links/{$link->id}", $payload)
            ->assertRedirect('/dashboard/links');
        $this->assertDatabaseHas('link_link_tag', [
            'link_id' => $link->id,
            'link_tag_id' => $tag->id,
        ]);

        $this->actingAs($owner)->patch("/dashboard/links/{$link->id}", [
            ...$payload,
            'tag_ids' => [],
        ])->assertRedirect('/dashboard/links');
        $this->assertDatabaseMissing('link_link_tag', [
            'link_id' => $link->id,
            'link_tag_id' => $tag->id,
        ]);
    }

    public function test_a_link_can_be_tagged_and_the_tags_are_available_for_filtering(): void
    {
        $owner = User::factory()->create();
        $tag = $owner->currentWorkspace->linkTags()->create([
            'name' => 'Launch',
            'slug' => 'launch',
            'color' => '#2563EB',
        ]);

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://example.com/launch',
            'slug' => 'launch-link',
            'is_active' => true,
            'tag_ids' => [$tag->id],
        ])->assertRedirect('/dashboard/links');

        $link = Link::query()->where('slug', 'launch-link')->sole();
        $this->assertDatabaseHas('link_link_tag', [
            'link_id' => $link->id,
            'link_tag_id' => $tag->id,
        ]);

        $this->withoutVite();
        $this->actingAs($owner)->get('/dashboard/links?tag='.$tag->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('links.data.0.id', $link->id)
                ->where('links.data.0.tags.0.slug', 'launch')
                ->where('filters.tag', (string) $tag->id)
                ->has('tagOptions', 1));
    }

    public function test_a_link_cannot_be_assigned_a_tag_from_another_workspace(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $foreignTag = $foreign->currentWorkspace->linkTags()->create([
            'name' => 'Private',
            'slug' => 'private',
        ]);

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://example.com/forged-tag',
            'slug' => 'forged-tag',
            'is_active' => true,
            'tag_ids' => [$foreignTag->id],
        ])->assertSessionHasErrors('tag_ids.0');

        $this->assertDatabaseMissing('links', ['slug' => 'forged-tag']);
    }
}
