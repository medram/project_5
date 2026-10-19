<?php

namespace Tests\Feature\Folders;

use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FolderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_private_folder(): void
    {
        $owner = User::factory()->create();

        $this
            ->actingAs($owner)
            ->post('/dashboard/folders', ['name' => '  Launch assets  '])
            ->assertRedirect('/dashboard/folders');

        $this->assertDatabaseHas('folders', [
            'user_id' => $owner->id,
            'name' => 'Launch assets',
        ]);
    }

    public function test_index_only_includes_the_signed_in_owners_folders(): void
    {
        $owner = User::factory()->create();
        $folder = $owner->folders()->create(['name' => 'Product launches']);
        $link = Link::factory()->for($owner)->create(['folder_id' => $folder->id]);
        Click::factory()->count(3)->for($link)->create();
        User::factory()->create()->folders()->create(['name' => 'Other account']);

        $this->withoutVite();

        $this
            ->actingAs($owner)
            ->get('/dashboard/folders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('folders/index', false)
                ->has('folders', 1)
                ->where('folders.0.name', 'Product launches')
                ->where('folders.0.links_count', 1)
                ->where('folders.0.clicks_count', 3),
            );
    }

    public function test_owner_can_rename_a_folder(): void
    {
        $owner = User::factory()->create();
        $folder = $owner->folders()->create(['name' => 'Unsorted']);

        $this
            ->actingAs($owner)
            ->patch("/dashboard/folders/{$folder->id}", ['name' => '  Client work  '])
            ->assertRedirect('/dashboard/folders');

        $this->assertDatabaseHas('folders', [
            'id' => $folder->id,
            'name' => 'Client work',
        ]);
    }

    public function test_other_user_cannot_mutate_a_folder(): void
    {
        $folder = User::factory()->create()
            ->folders()
            ->create(['name' => 'Private']);
        $otherUser = User::factory()->create();

        $this
            ->actingAs($otherUser)
            ->patch("/dashboard/folders/{$folder->id}", ['name' => 'Nope'])
            ->assertForbidden();

        $this
            ->actingAs($otherUser)
            ->delete("/dashboard/folders/{$folder->id}")
            ->assertForbidden();
    }

    public function test_deleting_a_folder_preserves_links_and_clears_their_assignment(): void
    {
        $owner = User::factory()->create();
        $folder = $owner->folders()->create(['name' => 'To remove']);
        $link = Link::factory()->for($owner)->create(['folder_id' => $folder->id]);

        $this
            ->actingAs($owner)
            ->delete("/dashboard/folders/{$folder->id}")
            ->assertRedirect('/dashboard/folders');

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'folder_id' => null,
        ]);
    }
}
