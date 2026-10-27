<?php

namespace Tests\Feature\Folders;

use App\Models\AnalyticsEvent;
use App\Models\Click;
use App\Models\User;
use Illuminate\Support\Str;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FolderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_create_a_folder_in_current_workspace_with_creator_attribution(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $this->actingAs($member)->post('/dashboard/folders', [
            'name' => '  Launch assets  ', 'workspace_id' => User::factory()->create()->current_workspace_id,
        ])->assertRedirect('/dashboard/folders');

        $this->assertDatabaseHas('folders', [
            'user_id' => $member->id, 'workspace_id' => $workspace->id, 'name' => 'Launch assets',
        ]);
    }

    public function test_index_lists_all_current_workspace_folders_and_no_foreign_folders(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Product launches']);
        $link = $workspace->links()->create([
            'user_id' => $owner->id, 'folder_id' => $folder->id, 'slug' => 'launch-1',
            'destination_url' => 'https://example.com', 'is_active' => true,
        ]);
        Click::factory()->count(3)->for($link)->create();
        $foreignOwner = User::factory()->create();
        $foreignOwner->currentWorkspace->folders()->create(['user_id' => $foreignOwner->id, 'name' => 'Other account']);

        $this->withoutVite();
        $this->actingAs($member)->get('/dashboard/folders')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('folders/index', false)
                ->has('folders.data', 1)->where('folders.data.0.name', 'Product launches')
                ->where('folders.data.0.links_count', 1)->where('folders.data.0.clicks_count', 3));
    }
    public function test_folder_rollups_count_only_same_workspace_links_clicks_and_events(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Campaign']);
        $link = $workspace->links()->create([
            'user_id' => $owner->id, 'folder_id' => $folder->id, 'slug' => 'local-rollup',
            'destination_url' => 'https://example.com', 'is_active' => true,
        ]);
        Click::factory()->count(2)->for($link)->create();

        $foreignOwner = User::factory()->create();
        $foreignWorkspace = $foreignOwner->currentWorkspace;
        $foreignLink = $foreignWorkspace->links()->create([
            'user_id' => $foreignOwner->id, 'folder_id' => $folder->id, 'slug' => 'foreign-rollup',
            'destination_url' => 'https://example.org', 'is_active' => true,
        ]);
        Click::factory()->count(5)->for($foreignLink)->create();
        $this->recordEvent($workspace->id, $owner->id, $link->id);
        $this->recordEvent($workspace->id, $owner->id, $link->id);
        $this->recordEvent($workspace->id, $owner->id, $link->id);
        $this->recordEvent($foreignWorkspace->id, $foreignOwner->id, $foreignLink->id);
        $this->recordEvent($foreignWorkspace->id, $foreignOwner->id, $foreignLink->id);
        $this->withoutVite();
        $this->actingAs($owner)->get('/dashboard/folders')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('folders.data.0.links_count', 1)
                ->where('folders.data.0.clicks_count', 2)
                ->where('folders.data.0.analytics_events_count', 3));
    }

    public function test_folder_list_uses_literal_search_safe_sort_direction_and_pagination(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $literal = $workspace->folders()->create([
            'user_id' => $owner->id, 'name' => 'Campaign 50%_! launch', 'created_at' => now()->subDay(),
        ]);
        $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Campaign 50xyZ! launch']);
        $foreign = User::factory()->create()->currentWorkspace->folders()->create([
            'user_id' => User::factory()->create()->id, 'name' => 'Campaign 50%_! launch',
        ]);
        for ($index = 0; $index < 21; $index++) {
            $workspace->folders()->create(['user_id' => $owner->id, 'name' => "Other {$index}"]);
        }

        $this->withoutVite();
        $this->actingAs($member)->get('/dashboard/folders?search='.urlencode('50%_!').'&sort=name&direction=asc&page=1&view=table')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders.data', 1)
                ->where('folders.data.0.id', $literal->id)
                ->where('folders.per_page', 20)
                ->where('filters.search', '50%_!')
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc')
                ->where('filters.view', 'table'));

        $this->actingAs($member)->getJson('/dashboard/folders?sort=destination_url&direction=up&view=grid')
            ->assertUnprocessable();
    }

    public function test_folder_list_sorts_link_count_and_paginates_within_active_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'One']);
        $two = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Two']);
        $workspace->links()->create([
            'user_id' => $owner->id, 'folder_id' => $two->id, 'slug' => 'folder-count',
            'destination_url' => 'https://example.com', 'is_active' => true,
        ]);
        User::factory()->create()->currentWorkspace->folders()->create([
            'user_id' => User::factory()->create()->id, 'name' => 'Foreign',
        ]);
        for ($index = 0; $index < 20; $index++) {
            $workspace->folders()->create(['user_id' => $owner->id, 'name' => "Folder {$index}"]);
        }

        $this->withoutVite();
        $this->actingAs($owner)->get('/dashboard/folders?sort=links_count&direction=desc&page=1&view=cards')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders.data', 20)
                ->where('folders.data.0.id', $two->id)
                ->where('folders.total', 22)
                ->where('filters.view', 'cards'));

        $this->actingAs($owner)->get('/dashboard/folders?sort=links_count&direction=desc&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('folders.data', 2)
                ->where('folders.current_page', 2)
                ->where('folders.total', 22));
    }

    public function test_deleting_the_only_folder_on_the_last_page_returns_to_page_one(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $this->travelTo(now());

        for ($index = 0; $index < 21; $index++) {
            $workspace->folders()->create([
                'user_id' => $owner->id,
                'name' => "Owned {$index}",
            ]);
        }
        $lastFolder = $workspace->folders()->where('name', 'Owned 20')->sole();

        $this->withoutVite();
        $this->actingAs($owner)->get('/dashboard/folders?search=Owned&page=2&view=table')
            ->assertInertia(fn (Assert $page) => $page
                ->where('folders.current_page', 2)
                ->where('folders.data.0.id', $lastFolder->id));

        $this->actingAs($owner)
            ->delete("/dashboard/folders/{$lastFolder->id}?search=Owned&page=2&view=table")
            ->assertRedirect(route('folders.index').'?search=Owned&view=table');

        $this->actingAs($owner)->get('/dashboard/folders?search=Owned&view=table')
            ->assertInertia(fn (Assert $page) => $page
                ->has('folders.data', 20)
                ->where('folders.current_page', 1)
                ->where('folders.total', 20));
    }

    public function test_member_can_rename_folder_created_by_another_workspace_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Unsorted']);

        $this->actingAs($member)->patch("/dashboard/folders/{$folder->id}", ['name' => 'Client work'])
            ->assertRedirect('/dashboard/folders');
        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Client work']);
    }

    public function test_foreign_workspace_folder_cannot_be_accessed_or_mutated(): void
    {
        $folderOwner = User::factory()->create();
        $folder = $folderOwner->currentWorkspace->folders()->create([
            'user_id' => $folderOwner->id, 'name' => 'Private',
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->patch("/dashboard/folders/{$folder->id}", ['name' => 'Nope'])->assertNotFound();
        $this->actingAs($otherUser)->delete("/dashboard/folders/{$folder->id}")->assertNotFound();
    }

    public function test_deleting_a_folder_preserves_links_and_clears_their_assignment(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'To remove']);
        $link = $workspace->links()->create([
            'user_id' => $owner->id, 'folder_id' => $folder->id, 'slug' => 'delete-folder-link',
            'destination_url' => 'https://example.com', 'is_active' => true,
        ]);

        $this->actingAs($owner)->delete("/dashboard/folders/{$folder->id}")->assertRedirect('/dashboard/folders');
        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'folder_id' => null]);
    }

    public function test_bulk_delete_preserves_links_and_clears_folder_assignments(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $first = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Bulk first']);
        $second = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Bulk second']);
        $link = $workspace->links()->create([
            'user_id' => $owner->id,
            'folder_id' => $first->id,
            'slug' => 'bulk-folder-link',
            'destination_url' => 'https://example.com/bulk-folder',
            'is_active' => true,
        ]);
        $foreignOwner = User::factory()->create();
        $foreign = $foreignOwner->currentWorkspace->folders()->create([
            'user_id' => $foreignOwner->id,
            'name' => 'Foreign bulk folder',
        ]);
        $state = http_build_query([
            'search' => 'bulk',
            'sort' => 'name',
            'direction' => 'asc',
            'page' => 2,
            'view' => 'table',
        ]);

        $this->actingAs($member)
            ->delete('/dashboard/folders?'.$state, ['ids' => [$first->id, $second->id]])
            ->assertRedirect(route('folders.index').'?search=bulk&sort=name&direction=asc&view=table');

        $this->assertDatabaseMissing('folders', ['id' => $first->id]);
        $this->assertDatabaseMissing('folders', ['id' => $second->id]);
        $this->assertDatabaseHas('folders', ['id' => $foreign->id]);
        $this->assertDatabaseHas('links', ['id' => $link->id, 'folder_id' => null]);
    }

    public function test_bulk_folder_delete_rejects_foreign_duplicate_empty_and_over_limit_selections(): void
    {
        $owner = User::factory()->create();
        $owned = $owner->currentWorkspace->folders()->create([
            'user_id' => $owner->id,
            'name' => 'Bulk validation owned',
        ]);
        $foreignOwner = User::factory()->create();
        $foreign = $foreignOwner->currentWorkspace->folders()->create([
            'user_id' => $foreignOwner->id,
            'name' => 'Bulk validation foreign',
        ]);

        $this->actingAs($owner)
            ->delete('/dashboard/folders', ['ids' => [$owned->id, $foreign->id]])
            ->assertSessionHasErrors('ids.1');
        $this->assertDatabaseHas('folders', ['id' => $owned->id]);
        $this->assertDatabaseHas('folders', ['id' => $foreign->id]);

        $this->actingAs($owner)
            ->delete('/dashboard/folders', ['ids' => [$owned->id, $owned->id]])
            ->assertSessionHasErrors('ids.1');
        $this->actingAs($owner)
            ->delete('/dashboard/folders', ['ids' => []])
            ->assertSessionHasErrors('ids');

        $ids = [$owned->id];
        for ($index = 0; $index < 20; $index++) {
            $ids[] = $owner->currentWorkspace->folders()->create([
                'user_id' => $owner->id,
                'name' => "Bulk limit {$index}",
            ])->id;
        }
        $this->actingAs($owner)
            ->delete('/dashboard/folders', ['ids' => $ids])
            ->assertSessionHasErrors('ids');

        $this->assertDatabaseHas('folders', ['id' => $owned->id]);
        $this->assertDatabaseHas('folders', ['id' => $foreign->id]);
    }

    public function test_folder_mutations_preserve_allowlisted_list_state(): void
    {
        $owner = User::factory()->create();
        $state = http_build_query([
            'search' => 'campaign',
            'sort' => 'links_count',
            'direction' => 'asc',
            'page' => 2,
            'view' => 'table',
            'next' => 'https://evil.example',
        ]);
        $mutationUrl = route('folders.index').'?'.http_build_query([
            'search' => 'campaign',
            'sort' => 'links_count',
            'direction' => 'asc',
            'view' => 'table',
        ]);

        $this->actingAs($owner)->from('https://evil.example/redirect')
            ->post('/dashboard/folders?'.$state, ['name' => 'Campaign assets'])
            ->assertRedirect($mutationUrl);
        $folder = $owner->currentWorkspace->folders()->where('name', 'Campaign assets')->sole();

        $this->patch("/dashboard/folders/{$folder->id}?".$state, ['name' => 'Campaign media'])
            ->assertRedirect($mutationUrl);
        $this->delete("/dashboard/folders/{$folder->id}?".$state)
            ->assertRedirect($mutationUrl);
    }
    private function recordEvent(int $workspaceId, int $userId, int $linkId): void
    {
        AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'link_id' => $linkId,
            'event_name' => 'link.clicked',
            'occurred_at' => now(),
            'visitor_hash' => hash('sha256', (string) Str::uuid()),
            'visitor_key_version' => 'test-v1',
        ]);
    }
}
