<?php

namespace Tests\Feature\Links;

use App\Models\CustomDomain;
use App\Models\Link;
use App\Models\LinkRevision;
use App\Models\User;
use App\Services\Links\LinkRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkRevisionReviewEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_history_exposes_safe_delivery_evidence_and_scopes_labels_to_the_workspace(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $foreignOwner = User::factory()->create();
        $foreignWorkspace = $foreignOwner->currentWorkspace;

        $link = Link::factory()->for($workspace)->create([
            'user_id' => $owner->id,
            'slug' => 'review-evidence',
        ]);
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Launch folder']);
        $tag = $workspace->linkTags()->create(['name' => 'Launch tag', 'slug' => 'launch-tag']);
        $domain = $workspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'go.example.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'active',
        ]);
        $page = $workspace->linkPages()->create([
            'user_id' => $owner->id,
            'name' => 'Launch page',
            'slug' => 'launch-page',
            'title' => 'Launch page title',
        ]);
        $overlay = $workspace->linkOverlays()->create([
            'user_id' => $owner->id,
            'name' => 'Launch overlay',
            'heading' => 'Heading',
            'body' => 'Body',
            'cta_label' => 'Continue',
        ]);
        $foreignFolder = $foreignWorkspace->folders()->create([
            'user_id' => $foreignOwner->id,
            'name' => 'Untrusted foreign folder',
        ]);
        $foreignTag = $foreignWorkspace->linkTags()->create([
            'name' => 'Untrusted foreign tag',
            'slug' => 'foreign-tag',
        ]);
        $foreignDomain = $foreignWorkspace->customDomains()->create([
            'user_id' => $foreignOwner->id,
            'hostname' => 'untrusted.example.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'active',
        ]);
        $foreignPage = $foreignWorkspace->linkPages()->create([
            'user_id' => $foreignOwner->id,
            'name' => 'Untrusted foreign page',
            'slug' => 'foreign-page',
            'title' => 'Foreign page title',
        ]);
        $foreignOverlay = $foreignWorkspace->linkOverlays()->create([
            'user_id' => $foreignOwner->id,
            'name' => 'Untrusted foreign overlay',
            'heading' => 'Heading',
            'body' => 'Body',
            'cta_label' => 'Continue',
        ]);

        $hash = Hash::make('never-render-this-secret');
        $link->revisions()->create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => $owner->id,
            'revision_number' => 2,
            'status' => 'pending',
            'snapshot' => [
                'destination_url' => 'https://direct.example.test/target',
                'routing_rules' => [
                    ['rule_type' => 'country', 'match_value' => 'US', 'target_url' => 'https://us.example.test'],
                    ['rule_type' => 'device', 'match_value' => 'mobile', 'target_url' => 'https://mobile.example.test'],
                ],
                'delivery_mode' => 'direct',
                'link_page_id' => $page->id,
                'link_overlay_id' => $overlay->id,
                'custom_domain_id' => $domain->id,
                'folder_id' => $folder->id,
                'tag_ids' => [$tag->id, $foreignTag->id],
                'title' => 'Review title',
                'slug' => 'review-evidence',
                'is_active' => true,
                'available_from' => '2026-10-22T10:00:00Z',
                'expires_at' => '2026-11-22T10:00:00Z',
                'max_clicks' => 75,
                'approval_required' => true,
                'approval_status' => 'pending',
                'utm_parameters' => ['utm_source' => 'newsletter'],
                'custom_parameters' => ['ref' => 'launch'],
                'access_code' => $hash,
                'access_code_version' => 2,
            ],
        ]);
        $link->revisions()->create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => $owner->id,
            'revision_number' => 1,
            'status' => 'stale',
            'snapshot' => [
                'custom_domain_id' => $foreignDomain->id,
                'link_page_id' => $foreignPage->id,
                'link_overlay_id' => $foreignOverlay->id,
                'folder_id' => $foreignFolder->id,
            ],
        ]);

        $this->actingAs($owner)
            ->get("/dashboard/links/{$link->id}/revisions?search=review&view=cards")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/revisions')
                ->where('revisions.0.target', 'https://direct.example.test/target')
                ->where('revisions.0.routingRules', [
                    ['position' => 1, 'condition' => 'country: US', 'destination' => 'https://us.example.test'],
                    ['position' => 2, 'condition' => 'device: mobile', 'destination' => 'https://mobile.example.test'],
                ])
                ->where('revisions.0.deliveryMode', 'direct')
                ->where('revisions.0.pageLabel', 'Launch page · /launch-page')
                ->where('revisions.0.overlayLabel', 'Launch overlay')
                ->where('revisions.0.customDomainLabel', 'go.example.test')
                ->where('revisions.0.folderLabel', 'Launch folder')
                ->where('revisions.0.tagLabels', ['Launch tag'])
                ->where('revisions.0.title', 'Review title')
                ->where('revisions.0.slug', 'review-evidence')
                ->where('revisions.0.isActive', true)
                ->where('revisions.0.availableFrom', '2026-10-22T10:00:00Z')
                ->where('revisions.0.expiresAt', '2026-11-22T10:00:00Z')
                ->where('revisions.0.maxClicks', 75)
                ->where('revisions.0.approvalRequired', true)
                ->where('revisions.0.approvalStatus', 'pending')
                ->where('revisions.0.utmParameters', ['utm_source' => 'newsletter'])
                ->where('revisions.0.customParameters', ['ref' => 'launch'])
                ->where('revisions.0.hasAccessCode', true)
                ->missing('revisions.0.accessCode')
                ->missing('revisions.0.accessCodeHash')
                ->where('listQuery', 'search=review&view=cards')
                ->where('revisions.1.folderLabel', null)
                ->where('revisions.1.tagLabels', [])
                ->where('revisions.1.customDomainLabel', null)
                ->where('revisions.1.pageLabel', null)
                ->where('revisions.1.overlayLabel', null)
                ->missing('revisions.0.snapshot')
                ->missing('revisions.1.snapshot'))
            ->assertDontSee($hash)
            ->assertDontSee('never-render-this-secret')
            ->assertDontSee('Untrusted foreign folder')
            ->assertDontSee('Untrusted foreign tag')
            ->assertDontSee('Untrusted foreign page')
            ->assertDontSee('Untrusted foreign overlay')
            ->assertDontSee('untrusted.example.test');
    }

    public function test_pending_filter_and_review_actions_exclude_foreign_workspace_revisions(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $foreignOwner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $link = Link::factory()->for($workspace)->create([
            'user_id' => $owner->id,
            'slug' => 'foreign-revision-owner',
        ]);
        $revision = LinkRevision::query()->create([
            'link_id' => $link->id,
            'workspace_id' => $foreignOwner->currentWorkspace->id,
            'actor_user_id' => $foreignOwner->id,
            'revision_number' => 1,
            'status' => 'pending',
            'snapshot' => ['title' => 'Foreign proposal'],
        ]);

        $this->actingAs($owner)
            ->get('/dashboard/links?status=pending')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('links.data', 0));

        $this->get("/dashboard/links/{$link->id}/revisions")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('revisions', 0))
            ->assertDontSee('Foreign proposal');

        $this->post("/dashboard/links/{$link->id}/revisions/{$revision->id}/approve")
            ->assertNotFound();
    }

    public function test_revision_history_uses_snapshot_labels_after_related_records_change(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $folder = $workspace->folders()->create([
            'user_id' => $owner->id,
            'name' => 'Launch folder',
        ]);
        $domain = $workspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'go.example.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'active',
        ]);
        $overlay = $workspace->linkOverlays()->create([
            'user_id' => $owner->id,
            'name' => 'Launch overlay',
            'heading' => 'Heading',
            'body' => 'Body',
            'cta_label' => 'Continue',
        ]);
        $page = $workspace->linkPages()->create([
            'user_id' => $owner->id,
            'name' => 'Launch page',
            'slug' => 'launch-page',
            'title' => 'Launch page title',
        ]);
        $tag = $workspace->linkTags()->create([
            'name' => 'Launch tag',
            'slug' => 'launch-tag',
        ]);
        $link = Link::factory()->for($workspace)->create([
            'user_id' => $owner->id,
            'slug' => 'immutable-labels',
            'approval_required' => true,
        ]);

        app(LinkRevisionService::class)->update($link, [
            'folder_id' => $folder->id,
            'custom_domain_id' => $domain->id,
            'link_overlay_id' => $overlay->id,
            'link_page_id' => $page->id,
            'delivery_mode' => 'link_page',
            'destination_url' => null,
            'approval_required' => true,
        ], [$tag->id], [], $owner);

        $folder->delete();
        $domain->update(['hostname' => 'renamed.example.test']);
        $overlay->delete();
        $page->delete();
        $tag->update(['name' => 'Renamed tag']);

        $this->actingAs($owner)
            ->get("/dashboard/links/{$link->id}/revisions")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('revisions.0.folderLabel', 'Launch folder')
                ->where('revisions.0.customDomainLabel', 'go.example.test')
                ->where('revisions.0.overlayLabel', 'Launch overlay')
                ->where('revisions.0.pageLabel', 'Launch page · /launch-page')
                ->where('revisions.0.tagLabels', ['Launch tag']));
    }
}
