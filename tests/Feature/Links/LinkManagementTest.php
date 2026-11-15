<?php

namespace Tests\Feature\Links;

use App\Models\Click;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
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
            ->post('/dashboard/links', [
                'destination_url' => 'https://example.com/launch',
                'title' => 'Launch',
                'slug' => 'Launch-26',
                'is_active' => true,
            ]);

        $response->assertRedirect('/dashboard/links');

        $this->assertDatabaseHas('links', [
            'slug' => 'launch-26',
            'destination_url' => 'https://example.com/launch',
            'is_active' => true,
        ]);
    }

    public function test_link_index_exposes_country_options_for_targeting_selects(): void
    {
        $this->withoutVite();

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('countries')
                ->where('countries', fn ($countries) => collect($countries)->contains(
                    fn ($country) => ($country['code'] ?? null) === 'US'
                        && ($country['name'] ?? null) === 'United States',
                )));
    }

    public function test_link_index_includes_only_current_workspace_link_page_metadata(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $linkPage = LinkPage::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'is_published' => true,
        ]);
        $this->createLink($owner, [
            'delivery_mode' => 'link_page',
            'destination_url' => null,
            'link_page_id' => $linkPage->id,
            'slug' => 'local-page-target',
        ]);
        $foreignOwner = User::factory()->create();
        $foreignPage = LinkPage::factory()->create([
            'user_id' => $foreignOwner->id,
            'workspace_id' => $foreignOwner->currentWorkspace->id,
            'is_published' => true,
        ]);
        $this->createLink($owner, [
            'delivery_mode' => 'link_page',
            'destination_url' => null,
            'link_page_id' => $foreignPage->id,
            'slug' => 'foreign-page-target',
        ]);

        $this->withoutVite();

        $this->actingAs($owner)->get('/dashboard/links?search=local-page-target')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 1)
                ->where('links.data.0.slug', 'local-page-target')
                ->where('links.data.0.link_page.id', $linkPage->id)
                ->where('links.data.0.link_page.name', $linkPage->name)
                ->where('links.data.0.link_page.slug', $linkPage->slug));

        $this->actingAs($owner)->get('/dashboard/links?search=foreign-page-target')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 1)
                ->where('links.data.0.slug', 'foreign-page-target')
                ->where('links.data.0.link_page', null));
    }

    public function test_link_creation_stores_tags_parameters_and_normalized_ordered_routing_rules(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $tag = $workspace->linkTags()->create([
            'name' => 'Launch',
            'slug' => 'launch',
        ]);

        $this->actingAs($owner)
            ->post('/dashboard/links', [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/default',
                'title' => 'Campaign route',
                'slug' => 'campaign-route',
                'is_active' => true,
                'tag_ids' => [$tag->id],
                'max_clicks' => 75,
                'utm_parameters' => ['utm_source' => 'newsletter'],
                'custom_parameters' => ['ref' => 'spring'],
                'routing_rules' => [
                    [
                        'rule_type' => 'country',
                        'match_value' => 'us',
                        'target_url' => 'https://example.org/us',
                    ],
                    [
                        'rule_type' => 'device',
                        'match_value' => 'MOBILE',
                        'target_url' => 'https://example.org/mobile',
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        $link = $workspace->links()->where('slug', 'campaign-route')->sole();
        $this->assertTrue($link->tags()->whereKey($tag->id)->exists());
        $this->assertSame(['utm_source' => 'newsletter'], $link->utm_parameters);
        $this->assertSame(['ref' => 'spring'], $link->custom_parameters);
        $this->assertSame([
            ['rule_type' => 'country', 'match_value' => 'US', 'position' => 1],
            ['rule_type' => 'device', 'match_value' => 'mobile', 'position' => 2],
        ], $link->routingRules()->get(['rule_type', 'match_value', 'position'])->toArray());
    }

    public function test_owner_gets_a_generated_seven_character_slug_when_custom_slug_is_omitted(): void
    {
        $response = $this
            ->actingAs(User::factory()->create())
            ->post('/dashboard/links', [
                'destination_url' => 'https://example.com/generated',
                'is_active' => true,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        $this->assertMatchesRegularExpression('/^[a-z0-9]{7}$/', Link::query()->sole()->slug);
    }

    public function test_generated_slug_retries_after_a_collision(): void
    {
        $owner = User::factory()->create();
        $this->createLink($owner, ['slug' => 'taken123']);

        Str::createRandomStringsUsingSequence(['taken123', 'fresh456']);

        try {
            $link = app(WorkspaceContext::class)->currentFor($owner)->links()->create([
                'user_id' => $owner->id,
                'destination_url' => 'https://example.com/retry',
                'is_active' => true,
            ]);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame('fresh456', $link->slug);
    }

    public function test_generated_slug_retries_when_the_candidate_is_reserved(): void
    {
        $owner = User::factory()->create();
        Str::createRandomStringsUsingSequence(['billing', 'fresh45']);

        try {
            $link = app(WorkspaceContext::class)->currentFor($owner)->links()->create([
                'user_id' => $owner->id,
                'destination_url' => 'https://example.com/retry',
                'is_active' => true,
            ]);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertSame('fresh45', $link->slug);
    }

    public function test_invalid_custom_slug_is_rejected(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->post('/dashboard/links', [
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
            ->post('/dashboard/links', [
                'destination_url' => 'https://example.com/duplicate',
                'slug' => 'TAKEN-26',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_every_configured_reserved_slug_is_rejected(): void
    {
        $owner = User::factory()->create();

        foreach ((array) config('linkpilot.reserved_slugs') as $slug) {
            $this
                ->actingAs($owner)
                ->post('/dashboard/links', [
                    'destination_url' => "https://example.com/{$slug}",
                    'slug' => $slug,
                    'is_active' => true,
                ])
                ->assertSessionHasErrors('slug');
        }
    }

    public function test_non_http_destination_is_rejected(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->post('/dashboard/links', [
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
            ->post('/dashboard/links', [
                'destination_url' => 'HTTP://LOCALHOST:8000/loop',
                'slug' => 'loop-link',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('destination_url');
    }

    public function test_destinations_reject_every_first_party_host_regardless_of_scheme_port_or_domain_status(): void
    {
        config()->set('linkpilot.default_hosts', ['alias.linkpilot.test']);
        $owner = User::factory()->create();
        $destinations = [
            'HTTPS://LOCALHOST:9443/loop',
            'https://ALIAS.LINKPILOT.TEST.:443/loop',
        ];

        foreach (['pending_dns', 'pending_tls', 'active', 'failed'] as $status) {
            $domain = $owner->currentWorkspace->customDomains()->create([
                'user_id' => $owner->id,
                'hostname' => str_replace('_', '-', $status).'.customer.test',
                'verification_token' => Str::lower(Str::random(40)),
                'status' => $status,
            ]);
            $destinations[] = "https://{$domain->hostname}:8443/loop";
        }

        foreach ($destinations as $index => $destination) {
            $response = $this
                ->actingAs($owner)
                ->post('/dashboard/links', [
                    'destination_url' => $destination,
                    'slug' => "blocked-{$index}",
                    'is_active' => true,
                ]);

            $this->assertDatabaseMissing('links', ['slug' => "blocked-{$index}"]);
            $response->assertSessionHasErrors('destination_url');
        }

        $this
            ->actingAs($owner)
            ->post('/dashboard/links', [
                'destination_url' => 'https://unrelated.example/path',
                'slug' => 'allowed-host',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');
    }

    public function test_owner_can_update_and_deactivate_a_link(): void
    {
        $owner = User::factory()->create();
        $link = $this->createLink($owner);

        $this
            ->actingAs($owner)
            ->patch("/dashboard/links/{$link->id}", [
                'destination_url' => 'https://example.com/updated',
                'title' => 'Updated',
                'slug' => 'updated-link',
                'is_active' => false,
            ])
            ->assertRedirect('/dashboard/links');

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'destination_url' => 'https://example.com/updated',
            'title' => 'Updated',
            'slug' => 'updated-link',
            'is_active' => false,
        ]);
    }

    public function test_owner_cannot_clear_an_existing_slug(): void
    {
        $owner = User::factory()->create();
        $link = $this->createLink($owner);

        $this
            ->actingAs($owner)
            ->patch("/dashboard/links/{$link->id}", [
                'destination_url' => $link->destination_url,
                'title' => $link->title,
                'slug' => '',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('links', [
            'id' => $link->id,
            'slug' => 'original-link',
        ]);
    }

    public function test_owner_can_delete_a_link(): void
    {
        $owner = User::factory()->create();
        $link = $this->createLink($owner);

        $this
            ->actingAs($owner)
            ->delete("/dashboard/links/{$link->id}")
            ->assertRedirect('/dashboard/links');

        $this->assertDatabaseMissing('links', ['id' => $link->id]);
    }

    public function test_other_workspace_user_cannot_update_a_link(): void
    {
        $link = $this->createLink(User::factory()->create());
        $this->actingAs(User::factory()->create())->patch("/dashboard/links/{$link->id}", [
            'destination_url' => 'https://example.com/takeover',
            'slug' => 'takeover',
            'is_active' => true,
        ])->assertNotFound();
    }

    public function test_other_workspace_user_cannot_delete_a_link(): void
    {
        $link = $this->createLink(User::factory()->create());
        $this->actingAs(User::factory()->create())->delete("/dashboard/links/{$link->id}")->assertNotFound();
    }

    public function test_links_are_visible_to_every_member_of_the_current_workspace_only(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $ownerLink = $this->createLink($owner, ['slug' => 'owner-26']);
        $otherWorkspaceLink = $this->createLink(User::factory()->create(), ['slug' => 'other-26']);

        $this->actingAs($member)->get('/dashboard/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('links.data', 1)->where('links.data.0.id', $ownerLink->id));

        $this->actingAs($member)->patch("/dashboard/links/{$ownerLink->id}", [
            'destination_url' => 'https://example.com/member-update',
            'slug' => 'member-update',
            'is_active' => true,
        ])->assertRedirect('/dashboard/links');
        $this->actingAs($member)->delete("/dashboard/links/{$ownerLink->id}")->assertRedirect('/dashboard/links');
        $this->actingAs($member)->patch("/dashboard/links/{$otherWorkspaceLink->id}", [
            'destination_url' => 'https://example.com/forged',
            'slug' => 'forged-link',
            'is_active' => true,
        ])->assertNotFound();
    }

    public function test_pending_edit_to_approved_link_appears_in_pending_list_without_changing_live_destination(): void
    {
        $owner = User::factory()->create();
        $link = $this->createLink($owner, [
            'slug' => 'pending-live-link',
            'approval_required' => true,
            'approval_status' => 'approved',
        ]);
        $member = User::factory()->create();
        $member->workspaces()->attach($owner->currentWorkspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $owner->currentWorkspace);

        $this->actingAs($member)->patch("/dashboard/links/{$link->id}", [
            'destination_url' => 'https://example.com/proposed',
            'title' => 'Proposed edit',
            'slug' => $link->slug,
            'is_active' => true,
            'approval_required' => true,
        ])->assertSessionHasNoErrors()->assertRedirect('/dashboard/links');
        $this->assertDatabaseHas('link_revisions', [
            'link_id' => $link->id,
            'workspace_id' => $link->workspace_id,
            'status' => 'pending',
        ]);

        $this->withoutVite();
        $this->actingAs($member)->get('/dashboard/links?status=pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 1)
                ->where('links.data.0.id', $link->id)
                ->where('links.data.0.approval_status', 'approved')
                ->where('links.data.0.has_pending_revision', true));

        $this->assertSame('https://example.com/original', $link->fresh()->destination_url);
        $this->get('/pending-live-link')->assertRedirect('https://example.com/original');
    }

    public function test_active_and_inactive_filters_exclude_initial_pending_links(): void
    {
        $owner = User::factory()->create();
        $active = $this->createLink($owner, [
            'slug' => 'approved-active',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
        $inactive = $this->createLink($owner, [
            'slug' => 'approved-inactive',
            'approval_status' => 'approved',
            'is_active' => false,
        ]);
        $pendingActive = $this->createLink($owner, [
            'slug' => 'pending-initial-active',
            'approval_required' => true,
            'approval_status' => 'pending',
            'is_active' => true,
        ]);
        $pendingInactive = $this->createLink($owner, [
            'slug' => 'pending-initial-inactive',
            'approval_required' => true,
            'approval_status' => 'pending',
            'is_active' => false,
        ]);

        $this->withoutVite();

        $this->actingAs($owner)->get('/dashboard/links?status=active')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 1)
                ->where('links.data.0.id', $active->id));

        $this->actingAs($owner)->get('/dashboard/links?status=inactive')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 1)
                ->where('links.data.0.id', $inactive->id));

        $this->actingAs($owner)->get('/dashboard/links?status=pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 2)
                ->where('links.data.0.approval_status', 'pending')
                ->where('links.data.1.approval_status', 'pending'));
    }

    public function test_link_list_uses_literal_search_workspace_filters_safe_sort_and_pagination(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Campaign']);
        $this->createLink($owner, [
            'title' => 'Offer 50%_! Launch', 'slug' => 'literal-search', 'folder_id' => $folder->id,
            'is_active' => true,
        ]);
        $sortedFirst = $this->createLink($owner, [
            'title' => 'A Offer 50%_! Launch', 'slug' => 'literal-sort-first', 'folder_id' => $folder->id,
            'is_active' => true,
        ]);
        $this->createLink($owner, [
            'title' => 'Offer 50xyZ! Launch', 'slug' => 'wildcard-decoy', 'folder_id' => $folder->id,
            'is_active' => false,
        ]);
        $this->createLink($owner, [
            'title' => 'Inactive Offer 50%_! Launch', 'slug' => 'literal-inactive', 'folder_id' => $folder->id,
            'is_active' => false,
        ]);
        $this->createLink(User::factory()->create(), [
            'title' => 'Offer 50%_! Launch', 'slug' => 'foreign-result',
        ]);
        $foreignFolderOwner = User::factory()->create();
        $foreignFolder = $foreignFolderOwner->currentWorkspace->folders()->create([
            'user_id' => $foreignFolderOwner->id, 'name' => 'Foreign campaign',
        ]);
        for ($index = 0; $index < 21; $index++) {
            $this->createLink($owner, ['title' => "Other {$index}", 'slug' => "other-{$index}"]);
        }

        $this->withoutVite();
        $this->actingAs($member)->get('/dashboard/links?search='.urlencode('50%_!').'&folder='.$folder->id.'&status=active&sort=title&direction=asc&page=1&view=table')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 2)
                ->where('links.data.0.id', $sortedFirst->id)
                ->where('links.current_page', 1)
                ->where('links.per_page', 20)
                ->where('filters.search', '50%_!')
                ->where('filters.folder', (string) $folder->id)
                ->where('filters.status', 'active')
                ->where('filters.sort', 'title')
                ->where('filters.direction', 'asc')
                ->where('filters.view', 'table'));

        $this->actingAs($member)->getJson('/dashboard/links?sort=destination_url&direction=desc&view=grid&folder='.$foreignFolder->id.'&status=any')
            ->assertUnprocessable();
    }

    public function test_link_list_paginates_and_does_not_include_other_workspaces(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        for ($index = 0; $index < 21; $index++) {
            $this->createLink($owner, ['title' => "Owned {$index}", 'slug' => "owned-{$index}"]);
        }
        $this->createLink(User::factory()->create(), ['title' => 'Foreign', 'slug' => 'foreign-page']);

        $this->withoutVite();
        $this->actingAs($member)->get('/dashboard/links?page=2&view=cards')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('links.data', 1)
                ->where('links.current_page', 2)
                ->where('links.total', 21)
                ->where('filters.view', 'cards'));
    }

    public function test_deleting_the_only_link_on_the_last_page_returns_to_page_one(): void
    {
        $owner = User::factory()->create();
        $this->travelTo(now());

        for ($index = 0; $index < 21; $index++) {
            $this->createLink($owner, [
                'title' => "Owned {$index}",
                'slug' => "owned-page-{$index}",
            ]);
        }
        $lastPageLink = $owner->currentWorkspace->links()
            ->where('slug', 'owned-page-20')
            ->sole();

        $this->withoutVite();
        $this->actingAs($owner)->get('/dashboard/links?search=Owned&page=2&view=table')
            ->assertInertia(fn (Assert $page) => $page
                ->where('links.current_page', 2)
                ->where('links.data.0.id', $lastPageLink->id));
        $this->actingAs($owner)
            ->delete("/dashboard/links/{$lastPageLink->id}?search=Owned&page=2&view=table")
            ->assertRedirect(route('links.index').'?search=Owned&view=table');

        $this->actingAs($owner)
            ->get('/dashboard/links?search=Owned&view=table')
            ->assertInertia(fn (Assert $page) => $page
                ->has('links.data', 20)
                ->where('links.current_page', 1)
                ->where('links.total', 20));
    }

    public function test_link_creation_uses_current_workspace_and_rejects_foreign_associations(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $folder = $workspace->folders()->create([
            'user_id' => $member->id, 'name' => 'Member folder',
        ]);
        $foreignOwner = User::factory()->create();
        $foreignFolder = $foreignOwner->currentWorkspace->folders()->create([
            'user_id' => $foreignOwner->id, 'name' => 'Foreign',
        ]);

        $this->actingAs($member)->post('/dashboard/links', [
            'destination_url' => 'https://example.com/member-link', 'slug' => 'member-link',
            'is_active' => true, 'folder_id' => $folder->id, 'workspace_id' => $foreignFolder->workspace_id,
        ])->assertRedirect('/dashboard/links');
        $this->assertDatabaseHas('links', [
            'slug' => 'member-link', 'workspace_id' => $workspace->id, 'user_id' => $member->id, 'folder_id' => $folder->id,
        ]);

        $this->actingAs($member)->post('/dashboard/links', [
            'destination_url' => 'https://example.com/forged-association', 'slug' => 'forged-association',
            'is_active' => true, 'folder_id' => $foreignFolder->id,
        ])->assertSessionHasErrors('folder_id');
    }

    public function test_foreign_workspace_domain_and_overlay_ids_are_rejected(): void
    {
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $domain = $foreignOwner->currentWorkspace->customDomains()->create([
            'user_id' => $foreignOwner->id,
            'hostname' => 'links.foreign.test',
            'verification_token' => 'a'.str_repeat('b', 39),
            'status' => 'active',
        ]);
        $overlay = LinkOverlay::factory()->for($foreignOwner)->create();

        $this->actingAs($owner)->post('/dashboard/links', [
            'destination_url' => 'https://example.com/foreign-integrations',
            'slug' => 'foreign-integrations',
            'is_active' => true,
            'custom_domain_id' => $domain->id,
            'link_overlay_id' => $overlay->id,
        ])->assertSessionHasErrors(['custom_domain_id', 'link_overlay_id']);
    }

    public function test_owner_can_assign_an_owned_folder_to_a_link(): void
    {
        $owner = User::factory()->create();
        $folder = $owner->currentWorkspace->folders()->create(['user_id' => $owner->id, 'name' => 'Campaigns']);

        $this
            ->actingAs($owner)
            ->post('/dashboard/links', [
                'destination_url' => 'https://example.com/campaign',
                'title' => 'Campaign',
                'slug' => 'campaign-26',
                'is_active' => true,
                'folder_id' => $folder->id,
            ])
            ->assertRedirect('/dashboard/links');

        $this->assertDatabaseHas('links', [
            'slug' => 'campaign-26',
            'folder_id' => $folder->id,
        ]);
    }

    public function test_folder_assignment_requires_ownership(): void
    {
        $foreignOwner = User::factory()->create();
        $folder = $foreignOwner->currentWorkspace->folders()->create(['user_id' => $foreignOwner->id, 'name' => 'Someone else']);

        $this
            ->actingAs(User::factory()->create())
            ->post('/dashboard/links', [
                'destination_url' => 'https://example.com/forged-folder',
                'slug' => 'forged-folder',
                'is_active' => true,
                'folder_id' => $folder->id,
            ])
            ->assertSessionHasErrors('folder_id');
    }

    public function test_availability_must_start_before_expiration(): void
    {
        $this
            ->actingAs(User::factory()->create())
            ->post('/dashboard/links', [
                'destination_url' => 'https://example.com/scheduled',
                'slug' => 'scheduled-26',
                'is_active' => true,
                'available_from' => '2026-10-21T12:00',
                'expires_at' => '2026-10-20T12:00',
            ])
            ->assertSessionHasErrors('expires_at');
    }

    public function test_bulk_archive_restore_and_activation_use_the_bulk_action_endpoint(): void
    {
        $owner = User::factory()->create();
        $active = $this->createLink($owner, ['slug' => 'bulk-active']);
        $paused = $this->createLink($owner, ['slug' => 'bulk-paused', 'is_active' => false]);

        $this->actingAs($owner)
            ->patch('/dashboard/links/bulk', ['action' => 'archive', 'ids' => [$active->id, $paused->id]])
            ->assertRedirect('/dashboard/links');
        $this->assertNotNull($active->fresh()->archived_at);
        $this->assertNotNull($paused->fresh()->archived_at);

        $this->patch('/dashboard/links/bulk', ['action' => 'restore', 'ids' => [$active->id, $paused->id]])
            ->assertRedirect('/dashboard/links');
        $this->assertNull($active->fresh()->archived_at);
        $this->assertNull($paused->fresh()->archived_at);

        $this->patch('/dashboard/links/bulk', ['action' => 'activate', 'ids' => [$paused->id]])
            ->assertRedirect('/dashboard/links');
        $this->assertTrue($paused->fresh()->is_active);

        $this->patch('/dashboard/links/bulk', ['action' => 'deactivate', 'ids' => [$active->id]])
            ->assertRedirect('/dashboard/links');
        $this->assertFalse($active->fresh()->is_active);
    }

    public function test_bulk_delete_removes_selected_links_and_click_history_with_list_state(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);

        $first = $this->createLink($owner, ['slug' => 'bulk-delete-first']);
        $second = $this->createLink($owner, ['slug' => 'bulk-delete-second']);
        Click::factory()->count(2)->for($first)->create();
        $clickIds = $first->clicks()->pluck('id')->all();
        $foreignOwner = User::factory()->create();
        $foreign = $this->createLink($foreignOwner, ['slug' => 'bulk-delete-foreign']);
        $state = http_build_query([
            'search' => 'campaign',
            'status' => 'active',
            'sort' => 'title',
            'direction' => 'asc',
            'page' => 2,
            'view' => 'table',
        ]);

        $this->actingAs($member)
            ->patch('/dashboard/links/bulk?'.$state, [
                'action' => 'delete',
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect(route('links.index').'?search=campaign&status=active&sort=title&direction=asc&view=table');

        $this->assertDatabaseMissing('links', ['id' => $first->id]);
        $this->assertDatabaseMissing('links', ['id' => $second->id]);
        $this->assertDatabaseHas('links', ['id' => $foreign->id]);
        foreach ($clickIds as $clickId) {
            $this->assertDatabaseMissing('clicks', ['id' => $clickId]);
        }
    }

    public function test_bulk_link_deletion_rejects_foreign_duplicate_empty_and_over_limit_selections(): void
    {
        $owner = User::factory()->create();
        $owned = $this->createLink($owner, ['slug' => 'bulk-validation-owned']);
        $foreign = $this->createLink(User::factory()->create(), ['slug' => 'bulk-validation-foreign']);

        $this->actingAs($owner)
            ->patch('/dashboard/links/bulk', ['action' => 'delete', 'ids' => [$owned->id, $foreign->id]])
            ->assertSessionHasErrors('ids.1');
        $this->assertDatabaseHas('links', ['id' => $owned->id]);
        $this->assertDatabaseHas('links', ['id' => $foreign->id]);

        $this->actingAs($owner)
            ->patch('/dashboard/links/bulk', ['action' => 'delete', 'ids' => [$owned->id, $owned->id]])
            ->assertSessionHasErrors('ids.1');
        $this->actingAs($owner)
            ->patch('/dashboard/links/bulk', ['action' => 'delete', 'ids' => []])
            ->assertSessionHasErrors('ids');

        $ids = [$owned->id];
        for ($index = 0; $index < 20; $index++) {
            $ids[] = $this->createLink($owner, ['slug' => "bulk-validation-{$index}"])->id;
        }
        $this->actingAs($owner)
            ->patch('/dashboard/links/bulk', ['action' => 'delete', 'ids' => $ids])
            ->assertSessionHasErrors('ids');

        $this->assertDatabaseHas('links', ['id' => $owned->id]);
        $this->assertDatabaseHas('links', ['id' => $foreign->id]);
    }

    public function test_link_mutations_preserve_only_allowlisted_list_state(): void
    {
        $owner = User::factory()->create();
        $folder = $owner->currentWorkspace->folders()->create([
            'user_id' => $owner->id,
            'name' => 'Campaigns',
        ]);
        $state = http_build_query([
            'search' => 'spring sale',
            'folder' => $folder->id,
            'status' => 'active',
            'sort' => 'title',
            'direction' => 'asc',
            'page' => 3,
            'view' => 'table',
            'next' => 'https://evil.example',
        ]);
        $mutationUrl = route('links.index').'?'.http_build_query([
            'search' => 'spring sale',
            'folder' => $folder->id,
            'status' => 'active',
            'sort' => 'title',
            'direction' => 'asc',
            'view' => 'table',
        ], '', '&', PHP_QUERY_RFC3986);

        $created = $this->actingAs($owner)
            ->from('https://evil.example/redirect')
            ->post('/dashboard/links?'.$state, [
                'destination_url' => 'https://example.com/spring',
                'slug' => 'spring-sale',
                'is_active' => true,
            ]);
        $created->assertRedirect($mutationUrl);
        $link = Link::query()->where('slug', 'spring-sale')->sole();

        $this->patch("/dashboard/links/{$link->id}?".$state, [
            'destination_url' => 'https://example.com/spring-updated',
            'title' => 'Spring Sale',
            'slug' => 'spring-sale',
            'is_active' => true,
        ])->assertRedirect($mutationUrl);

        $this->delete("/dashboard/links/{$link->id}?".$state)
            ->assertRedirect($mutationUrl);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createLink(User $owner, array $attributes = []): Link
    {
        $workspace = app(WorkspaceContext::class)->currentFor($owner);

        return $workspace->links()->create(array_merge([
            'user_id' => $owner->id,
            'destination_url' => 'https://example.com/original',
            'title' => 'Original',
            'slug' => 'original-link',
            'is_active' => true,
        ], $attributes));
    }
}
