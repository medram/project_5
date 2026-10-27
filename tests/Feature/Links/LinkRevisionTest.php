<?php

namespace Tests\Feature\Links;

use App\Models\CustomDomain;
use App\Models\Link;
use App\Models\LinkRevision;
use App\Models\LinkOverlay;
use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_gated_edits_stay_pending_until_an_owner_approves_them(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'review-link');
        $member = User::factory()->create();
        $member->workspaces()->attach($owner->currentWorkspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $owner->currentWorkspace);

        $this->actingAs($member)
            ->patch("/dashboard/links/{$link->id}", [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/proposed',
                'title' => 'Proposed title',
                'slug' => $link->slug,
                'is_active' => true,
                'approval_required' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        $link->refresh();
        $revision = $link->revisions()->where('status', 'pending')->orderByDesc('revision_number')->firstOrFail();
        $this->assertSame('https://example.com/original', $link->destination_url);
        $this->assertSame('pending', $link->approval_status);
        $this->get('/review-link')->assertNotFound();

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertRedirect("/dashboard/links/{$link->id}/revisions");

        $link->refresh();
        $this->assertSame('https://example.com/proposed', $link->destination_url);
        $this->assertSame('approved', $link->approval_status);
        $this->assertSame('approved', $revision->fresh()->status);
        $this->get('/review-link')->assertRedirect('https://example.com/proposed');
        $this->assertDatabaseCount('clicks', 1);

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertStatus(409);
    }

    public function test_stale_revision_is_rejected_after_a_newer_revision_is_applied(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'stale-link');
        $member = User::factory()->create();
        $member->workspaces()->attach($owner->currentWorkspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $owner->currentWorkspace);

        $first = $this->proposeDestination($member, $link, 'https://example.com/first');
        $second = $this->proposeDestination($member, $link, 'https://example.com/second');

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$first->id}/approve")
            ->assertRedirect();
        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$second->id}/approve")
            ->assertStatus(409);

        $this->assertSame('stale', $second->fresh()->status);
        $this->assertSame('https://example.com/first', $link->fresh()->destination_url);
        $this->assertSame(2, $link->fresh()->revision_version);
    }

    public function test_only_workspace_owners_and_admins_can_review_link_revisions(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'permission-link');
        $revision = $link->revisions()->where('status', 'pending')->firstOrFail();
        $member = User::factory()->create();
        $member->workspaces()->attach($owner->currentWorkspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $owner->currentWorkspace);

        $this->actingAs($member)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertForbidden();
        $this->assertSame('pending', $revision->fresh()->status);
    }

    public function test_workspace_admin_can_approve_and_reject_link_revisions(): void
    {
        $owner = User::factory()->create();
        $approvedLink = $this->createApprovalGatedLink($owner, 'admin-approve-link');
        $rejectedLink = $this->createApprovalGatedLink($owner, 'admin-reject-link');
        $workspace = $owner->currentWorkspace;
        $admin = User::factory()->create();
        $admin->workspaces()->attach($workspace, ['role' => 'admin']);
        app(WorkspaceContext::class)->activate($admin, $workspace);
        $approvedRevision = $approvedLink->revisions()->where('status', 'pending')->firstOrFail();
        $rejectedRevision = $rejectedLink->revisions()->where('status', 'pending')->firstOrFail();
        $this->assertSame('pending', $approvedRevision->status);
        $this->assertSame(
            (int) $approvedLink->revision_version,
            (int) $approvedRevision->snapshot['base_version'],
        );
        $this->assertSame('https://example.com/original', $approvedRevision->snapshot['destination_url']);

        $this->actingAs($admin)
            ->post("/dashboard/links/{$approvedLink->id}/revisions/{$approvedRevision->id}/approve")
            ->assertRedirect("/dashboard/links/{$approvedLink->id}/revisions");

        $this->assertSame('approved', $approvedRevision->fresh()->status);
        $this->assertSame($admin->id, $approvedRevision->fresh()->reviewer_user_id);

        $this->actingAs($admin)
            ->post("/dashboard/links/{$rejectedLink->id}/revisions/{$rejectedRevision->id}/reject", [
                'note' => 'Workspace admin review',
            ])
            ->assertRedirect("/dashboard/links/{$rejectedLink->id}/revisions");

        $this->assertSame('rejected', $rejectedRevision->fresh()->status);
        $this->assertSame('Workspace admin review', $rejectedRevision->fresh()->review_note);
        $this->assertSame($admin->id, $rejectedRevision->fresh()->reviewer_user_id);
    }

    public function test_revision_history_exposes_access_code_presence_without_exposing_its_hash(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $this->actingAs($owner)
            ->post('/dashboard/links', [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/private',
                'slug' => 'private-code',
                'is_active' => true,
                'access_code' => 'testing-secret',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        $link = Link::query()->where('slug', 'private-code')->sole();
        $revision = $link->revisions()->firstOrFail();
        $hash = $revision->snapshot['access_code'];
        $this->assertTrue(Hash::check('testing-secret', $hash));

        $this->actingAs($owner)
            ->get("/dashboard/links/{$link->id}/revisions")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/revisions')
                ->where('revisions.0.hasAccessCode', true)
                ->missing('revisions.0.snapshot'))
            ->assertDontSee($hash)
            ->assertDontSee('testing-secret');
    }

    public function test_pending_revision_keeps_live_child_collections_unchanged_and_approval_applies_its_snapshot(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'snapshot-link');
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $originalTag = $workspace->linkTags()->create(['name' => 'Original', 'slug' => 'original']);
        $proposedTag = $workspace->linkTags()->create(['name' => 'Proposed', 'slug' => 'proposed']);
        $link->tags()->attach($originalTag->id);
        $link->routingRules()->create([
            'workspace_id' => $workspace->id,
            'position' => 1,
            'rule_type' => 'country',
            'match_value' => 'US',
            'target_url' => 'https://example.com/us',
        ]);

        $member = User::factory()->create();
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $this->actingAs($member)
            ->patch("/dashboard/links/{$link->id}", [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/proposed',
                'title' => 'Proposed snapshot',
                'slug' => $link->slug,
                'is_active' => true,
                'approval_required' => true,
                'tag_ids' => [$proposedTag->id],
                'routing_rules' => [[
                    'rule_type' => 'device',
                    'match_value' => 'mobile',
                    'target_url' => 'https://example.com/mobile',
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        $revision = $link->revisions()->where('status', 'pending')->orderByDesc('revision_number')->firstOrFail();
        $this->assertSame(1, $revision->snapshot['base_version']);
        $this->assertSame([$proposedTag->id], $revision->snapshot['tag_ids']);
        $this->assertSame([[
            'rule_type' => 'device',
            'match_value' => 'mobile',
            'target_url' => 'https://example.com/mobile',
        ]], $revision->snapshot['routing_rules']);
        $this->assertSame('https://example.com/original', $link->fresh()->destination_url);
        $this->assertSame([$originalTag->id], $link->tags()->pluck('link_tags.id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('country', $link->routingRules()->sole()->rule_type);

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertRedirect("/dashboard/links/{$link->id}/revisions");

        $this->assertSame('https://example.com/proposed', $link->fresh()->destination_url);
        $this->assertSame([$proposedTag->id], $link->tags()->pluck('link_tags.id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame('device', $link->routingRules()->sole()->rule_type);
    }

    public function test_rejection_requires_review_permission_and_cannot_be_repeated(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'reject-link');
        $revision = $link->revisions()->where('status', 'pending')->firstOrFail();
        $member = User::factory()->create();
        $member->workspaces()->attach($owner->currentWorkspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $owner->currentWorkspace);

        $this->actingAs($member)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/reject", ['note' => 'Not approved'])
            ->assertForbidden();
        $this->assertSame('pending', $revision->fresh()->status);

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/reject", ['note' => 'Please revise the destination.'])
            ->assertRedirect("/dashboard/links/{$link->id}/revisions");

        $this->assertSame('rejected', $revision->fresh()->status);
        $this->assertSame('Please revise the destination.', $revision->fresh()->review_note);
        $this->assertSame('https://example.com/original', $link->fresh()->destination_url);
        $this->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/reject")
            ->assertStatus(409);
    }

    public function test_revision_history_and_review_preserve_validated_link_list_state(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $link = $this->createApprovalGatedLink($owner, 'history-state-link');
        $folder = $workspace->folders()->create([
            'user_id' => $owner->id,
            'name' => 'Campaigns',
        ]);
        $tag = $workspace->linkTags()->create([
            'name' => 'Launch',
            'slug' => 'launch',
        ]);
        $member = User::factory()->create();
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $revision = $this->proposeDestination($member, $link, 'https://example.com/proposed');
        $query = [
            'search' => 'history',
            'folder' => $folder->id,
            'tag' => $tag->id,
            'status' => 'active',
            'sort' => 'title',
            'direction' => 'asc',
            'page' => 2,
            'view' => 'table',
        ];
        $queryString = http_build_query($query);

        $this->actingAs($owner)
            ->get("/dashboard/links/{$link->id}/revisions?{$queryString}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('listQuery', $queryString));

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve?{$queryString}")
            ->assertRedirect(route('links.revisions.index', ['link' => $link->id] + $query));
    }

    public function test_approval_stales_a_pending_destination_that_became_a_claimed_host(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'claimed-target-review');
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $revision = $this->proposeDestination(
            $member,
            $link,
            'https://links.claimed.test/target',
        );

        $foreignOwner = User::factory()->create();
        $foreignOwner->currentWorkspace->customDomains()->create([
            'user_id' => $foreignOwner->id,
            'hostname' => 'links.claimed.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertStatus(409);

        $this->assertSame('stale', $revision->fresh()->status);
        $this->assertSame('https://example.com/original', $link->fresh()->destination_url);
    }

    public function test_approval_stales_a_pending_slug_that_became_reserved(): void
    {
        $reservedSlug = 'later-reserved';
        config()->set('linkpilot.reserved_slugs', array_values(array_diff(
            (array) config('linkpilot.reserved_slugs', []),
            [$reservedSlug],
        )));

        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'reserved-target-review');
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);

        $this->actingAs($member)
            ->patch("/dashboard/links/{$link->id}", [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/reserved',
                'title' => 'Reserved proposal',
                'slug' => $reservedSlug,
                'is_active' => true,
                'approval_required' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');
        $revision = $link->revisions()->where('status', 'pending')->orderByDesc('revision_number')->firstOrFail();

        config()->set('linkpilot.reserved_slugs', [
            ...((array) config('linkpilot.reserved_slugs', [])),
            $reservedSlug,
        ]);

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertStatus(409);

        $this->assertSame('stale', $revision->fresh()->status);
        $this->assertSame('reserved-target-review', $link->fresh()->slug);
    }

    public function test_approval_stales_a_pending_slug_claimed_by_another_link(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'claimed-slug-review');
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $proposedSlug = 'claimed-after-proposal';

        $this->actingAs($member)
            ->patch("/dashboard/links/{$link->id}", [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/proposed',
                'title' => 'Claimed slug proposal',
                'slug' => $proposedSlug,
                'is_active' => true,
                'approval_required' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');
        $revision = $link->revisions()->where('status', 'pending')->orderByDesc('revision_number')->firstOrFail();
        $otherOwner = User::factory()->create();
        Link::factory()->for($otherOwner)->create(['slug' => $proposedSlug]);

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertStatus(409);

        $this->assertSame('stale', $revision->fresh()->status);
        $this->assertSame('claimed-slug-review', $link->fresh()->slug);
        $this->assertSame('https://example.com/original', $link->fresh()->destination_url);
    }

    public function test_approval_returns_a_conflict_when_the_slug_is_claimed_after_validation(): void
    {
        $owner = User::factory()->create();
        $link = $this->createApprovalGatedLink($owner, 'concurrent-slug-review');
        $member = User::factory()->create();
        $member->workspaces()->attach($owner->currentWorkspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $owner->currentWorkspace);
        $proposedSlug = 'concurrently-claimed-slug';

        $this->actingAs($member)
            ->patch("/dashboard/links/{$link->id}", [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/proposed',
                'title' => 'Concurrent slug proposal',
                'slug' => $proposedSlug,
                'is_active' => true,
                'approval_required' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');
        $revision = $link->revisions()->where('status', 'pending')->orderByDesc('revision_number')->firstOrFail();

        Link::saving(static function (Link $candidate) use ($link, $owner, $proposedSlug): void {
            if ($candidate->getKey() === $link->getKey() && $candidate->slug === $proposedSlug) {
                Link::factory()->for($owner)->create(['slug' => $proposedSlug]);
            }
        });

        try {
            $this->actingAs($owner)
                ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
                ->assertStatus(409);
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.saving: App\\Models\\Link');
        }

        $this->assertSame('stale', $revision->fresh()->status);
        $this->assertSame('concurrent-slug-review', $link->fresh()->slug);
    }

    public function test_approval_stales_overlay_delivery_when_its_overlay_becomes_inactive(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $link = $this->createApprovalGatedLink($owner, 'inactive-overlay-review');
        $overlay = $workspace->linkOverlays()->create([
            'user_id' => $owner->id,
            'name' => 'Pending overlay',
            'heading' => 'Heading',
            'body' => 'Body',
            'cta_label' => 'Continue',
        ]);
        $member = User::factory()->create();
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);

        $this->actingAs($member)
            ->patch("/dashboard/links/{$link->id}", [
                'delivery_mode' => 'overlay',
                'destination_url' => 'https://example.com/proposed',
                'link_overlay_id' => $overlay->id,
                'title' => 'Overlay proposal',
                'slug' => $link->slug,
                'is_active' => true,
                'approval_required' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');
        $revision = $link->revisions()->where('status', 'pending')->orderByDesc('revision_number')->firstOrFail();
        $overlay->update(['is_active' => false]);

        $this->actingAs($owner)
            ->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertStatus(409);

        $this->assertSame('stale', $revision->fresh()->status);
        $this->assertSame('direct', $link->fresh()->delivery_mode);
        $this->assertNull($link->fresh()->link_overlay_id);
        $this->assertSame('https://example.com/original', $link->fresh()->destination_url);
    }

    private function createApprovalGatedLink(User $owner, string $slug): Link
    {
        $this->actingAs($owner)
            ->post('/dashboard/links', [
                'delivery_mode' => 'direct',
                'destination_url' => 'https://example.com/original',
                'slug' => $slug,
                'is_active' => true,
                'approval_required' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        return Link::query()->where('slug', $slug)->sole();
    }

    private function proposeDestination(User $member, Link $link, string $destination): LinkRevision
    {
        $this->actingAs($member)
            ->patch("/dashboard/links/{$link->id}", [
                'delivery_mode' => 'direct',
                'destination_url' => $destination,
                'title' => 'Pending change',
                'slug' => $link->slug,
                'is_active' => true,
                'approval_required' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/links');

        return $link->revisions()->where('status', 'pending')->orderByDesc('revision_number')->firstOrFail();
    }
}
