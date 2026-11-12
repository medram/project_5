<?php

namespace Tests\Feature\Admin;

use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\TrackingPixel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_metadata_requires_permission_and_support_can_read(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/links')
            ->assertForbidden();

        $this->withoutVite();
        $this->actingAs(User::factory()->support()->create())
            ->get('/admin/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/index')
                ->where('resource.key', 'links'));
    }

    public function test_all_content_lists_expose_cross_owner_safe_metadata_only(): void
    {
        [$first, $second] = $this->twoOwners();
        $firstLink = $this->link($first, 'First private destination');
        $secondLink = $this->link($second, 'Second private destination');
        $bio = BioPage::query()->create([
            'workspace_id' => $first->current_workspace_id,
            'user_id' => $first->id,
            'handle' => 'first-bio',
            'display_name' => 'First Bio',
            'bio' => 'private bio copy',
            'is_published' => true,
        ]);
        $secondBio = BioPage::query()->create([
            'workspace_id' => $second->current_workspace_id,
            'user_id' => $second->id,
            'handle' => 'second-bio',
            'display_name' => 'Second Bio',
            'bio' => 'second private bio copy',
            'is_published' => false,
        ]);
        Folder::query()->create(['workspace_id' => $first->current_workspace_id, 'user_id' => $first->id, 'name' => 'First Folder']);
        Folder::query()->create(['workspace_id' => $second->current_workspace_id, 'user_id' => $second->id, 'name' => 'Second Folder']);
        $domain = CustomDomain::query()->forceCreate([
            'workspace_id' => $first->current_workspace_id,
            'user_id' => $first->id,
            'hostname' => 'first-domain.test',
            'verification_token' => 'secret-domain-token',
            'status' => 'pending_dns',
            'failure_reason' => 'private failure body',
        ]);
        CustomDomain::query()->forceCreate([
            'workspace_id' => $second->current_workspace_id,
            'user_id' => $second->id,
            'hostname' => 'second-domain.test',
            'verification_token' => 'second-secret-token',
            'status' => 'active',
        ]);
        LinkOverlay::query()->create([
            'workspace_id' => $first->current_workspace_id,
            'user_id' => $first->id,
            'name' => 'First Overlay',
            'heading' => 'private overlay heading',
            'body' => 'private overlay body',
            'cta_label' => 'Open',
            'is_active' => true,
        ]);
        $linkPage = LinkPage::query()->create([
            'workspace_id' => $first->current_workspace_id,
            'user_id' => $first->id,
            'name' => 'First Page',
            'title' => 'First Page',
            'slug' => 'first-page',
            'description' => 'private page description',
            'is_published' => true,
        ]);
        $pixel = TrackingPixel::query()->create([
            'workspace_id' => $first->current_workspace_id,
            'user_id' => $first->id,
            'name' => 'First Pixel',
            'provider' => 'Meta',
            'provider_id' => 'provider-secret-id',
            'is_active' => true,
        ]);

        $this->withoutVite();
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->get('/admin/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/index')
                ->has('rows.data', 2)
                ->where('rows.data.0.workspace', $first->currentWorkspace->name)
                ->where('rows.data.0.owner', $first->name)
                ->where('rows.data.1.workspace', $second->currentWorkspace->name)
                ->missing('rows.data.0.destinationUrl')
                ->missing('rows.data.0.accessCode'))
            ->assertDontSee($firstLink->destination_url)
            ->assertDontSee($secondLink->destination_url)
            ->assertDontSee('private destination');

        $this->actingAs($administrator)
            ->get('/admin/biolinks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)
                ->where('rows.data.0.identifier', '@first-bio')
                ->where('rows.data.1.identifier', '@second-bio'))
            ->assertDontSee($bio->bio)
            ->assertDontSee($secondBio->bio);

        $this->actingAs($administrator)
            ->get('/admin/folders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 2));

        $this->actingAs($administrator)
            ->get('/admin/custom-domains')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)
                ->where('rows.data.0.identifier', 'first-domain.test'))
            ->assertDontSee($domain->verification_token)
            ->assertDontSee('private failure body');

        $this->actingAs($administrator)
            ->get('/admin/link-overlays')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1))
            ->assertDontSee('private overlay heading')
            ->assertDontSee('private overlay body');

        $this->actingAs($administrator)
            ->get('/admin/link-pages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.identifier', $linkPage->slug))
            ->assertDontSee('private page description');

        $this->actingAs($administrator)
            ->get('/admin/pixels')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.provider', 'Meta'))
            ->assertDontSee($pixel->provider_id);
    }

    public function test_content_search_filters_sort_and_pagination_are_url_backed(): void
    {
        [$owner] = $this->twoOwners();

        foreach (range(1, 25) as $index) {
            $this->link($owner, "https://example.test/{$index}", "Link {$index}", "link-{$index}");
        }

        $this->withoutVite();
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->get('/admin/links?page=2&sort=name&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.current_page', 2)
                ->has('rows.data', 5)
                ->where('filters.sort', 'name')
                ->where('filters.direction', 'asc'));

        $this->actingAs($administrator)
            ->get('/admin/links?q=Link%C2%A012')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));

        $this->actingAs($administrator)
            ->get('/admin/links?q='.urlencode("' OR 1=1 --"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function twoOwners(): array
    {
        $first = User::factory()->create(['name' => 'First Owner']);
        $second = User::factory()->create(['name' => 'Second Owner']);

        return [$first, $second];
    }

    private function link(
        User $owner,
        string $destination,
        string $title = 'Retained Link',
        ?string $slug = null,
    ): Link {
        return Link::factory()->for($owner)->create([
            'workspace_id' => $owner->current_workspace_id,
            'slug' => $slug ?? 'retained-'.substr(sha1($destination), 0, 10),
            'title' => $title,
            'destination_url' => $destination,
            'access_code' => 'private-access-code',
            'is_active' => true,
        ]);
    }
}