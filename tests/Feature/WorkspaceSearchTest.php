<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_search_includes_safe_integration_metadata_and_qr_campaigns(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $link = $workspace->links()->create([
            'user_id' => $owner->id,
            'title' => 'Integration Needle link',
            'slug' => 'integration-needle',
            'destination_url' => 'https://hidden-destination.example/private',
            'access_code' => 'private-access-hash-value',
            'is_active' => true,
        ]);
        $linkedQr = $link->qrCode()->firstOrFail();
        $campaign = $workspace->qrCodes()->create([
            'created_by' => $owner->id,
            'source' => 'campaign',
            'code' => 'needleqr1234',
            'title' => 'Integration Needle QR campaign',
            'destination_url' => 'https://qr-hidden-destination.example/private',
            'access_code' => Hash::make('private-qr-access'),
            'logo_path' => 'private-qr-logo.png',
            'is_active' => true,
        ]);
        $domain = $workspace->customDomains()->create([
            'user_id' => $owner->id, 'hostname' => 'needle.example', 'status' => 'active',
            'verification_token' => 'private-verification-token',
        ]);
        $overlay = $workspace->linkOverlays()->create([
            'user_id' => $owner->id, 'name' => 'Integration Needle overlay',
            'heading' => 'Needle', 'body' => 'Text', 'cta_label' => 'Open',
            'is_active' => true,
        ]);
        $pixel = $workspace->trackingPixels()->create([
            'user_id' => $owner->id, 'name' => 'Integration Needle pixel',
            'provider' => 'meta', 'provider_id' => 'private-provider-id', 'is_active' => true,
        ]);
        $webhook = Webhook::query()->forceCreate([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'name' => 'Integration Needle webhook',
            'url' => 'https://private-webhook.example/hook',
            'secret' => 'private-webhook-secret',
            'events' => json_encode(['link.clicked']),
            'revision' => 1,
            'is_active' => true,
        ]);

        $outsider = User::factory()->create();
        $outsider->currentWorkspace->customDomains()->create([
            'user_id' => $outsider->id, 'hostname' => 'outsider-needle.example',
            'status' => 'active', 'verification_token' => 'outside-token',
        ]);
        $outsider->currentWorkspace->qrCodes()->create([
            'created_by' => $outsider->id,
            'source' => 'campaign',
            'code' => 'outsideqr1234',
            'title' => 'Integration Needle outsider QR campaign',
            'destination_url' => 'https://outside.example/private',
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->getJson(route('search.index', ['q' => 'Needle'], false))
            ->assertOk()
            ->assertJsonCount(7, 'results')
            ->assertJsonPath('results.0.type', 'link')
            ->assertJsonPath('results.1.type', 'qr_code')
            ->assertJsonPath('results.2.type', 'qr_code')
            ->assertJsonFragment([
                'id' => $linkedQr->id,
                'type' => 'qr_code',
                'title' => 'Integration Needle link',
                'description' => 'QR code for /integration-needle',
                'href' => route('qr-codes.index', [], false).'#qr-campaign-'.$linkedQr->id,
            ])
            ->assertJsonFragment([
                'id' => $campaign->id,
                'type' => 'qr_code',
                'title' => 'Integration Needle QR campaign',
                'description' => 'QR campaign',
                'href' => route('qr-codes.index', [], false).'#qr-campaign-'.$campaign->id,
            ])
            ->assertJsonPath('results.3.type', 'custom_domain')
            ->assertJsonPath('results.3.title', 'needle.example')
            ->assertJsonPath('results.3.href', route('custom-domains.index', [], false).'#custom-domain-'.$domain->id)
            ->assertJsonPath('results.4.type', 'overlay')
            ->assertJsonPath('results.4.href', route('overlays.index', [], false).'#overlay-'.$overlay->id)
            ->assertJsonPath('results.5.type', 'tracking_pixel')
            ->assertJsonPath('results.5.description', 'Meta pixel')
            ->assertJsonPath('results.5.href', route('tracking-pixels.index', [], false).'#tracking-pixel-'.$pixel->id)
            ->assertJsonPath('results.6.type', 'webhook')
            ->assertJsonPath('results.6.href', route('webhooks.index', [], false).'#webhook-'.$webhook->id)
            ->assertJsonMissing(['title' => 'outsider-needle.example'])
            ->assertJsonMissing(['title' => 'Integration Needle outsider QR campaign'])
            ->assertDontSee('hidden-destination.example')
            ->assertDontSee('private-access-hash-value')
            ->assertDontSee($campaign->destination_url)
            ->assertDontSee($campaign->access_code)
            ->assertDontSee($campaign->logo_path)
            ->assertDontSee('private-provider-id')
            ->assertDontSee('private-webhook.example')
            ->assertDontSee('private-webhook-secret')
            ->assertDontSee('private-verification-token');

        $this->actingAs($owner)
            ->getJson(route('search.index', ['q' => $campaign->code], false))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $campaign->id)
            ->assertJsonPath('results.0.type', 'qr_code');

        $this->actingAs($owner)
            ->getJson(route('search.index', ['q' => 'qr-hidden-destination'], false))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_workspace_search_respects_custom_domain_visibility(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $activeDomain = $workspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'visible-active.example',
            'status' => 'active',
            'verification_token' => 'active-domain-token',
        ]);
        $pendingDomain = $workspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'visible-pending.example',
            'status' => 'pending_dns',
            'verification_token' => 'pending-domain-token',
        ]);
        $member = User::factory()->create();
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);

        $this->actingAs($member)
            ->getJson(route('search.index', ['q' => 'visible'], false))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.title', $activeDomain->hostname)
            ->assertJsonPath('results.0.href', route('custom-domains.index', [], false).'#custom-domain-'.$activeDomain->id)
            ->assertJsonMissing(['title' => $pendingDomain->hostname]);

        $this->actingAs($owner)
            ->getJson(route('search.index', ['q' => 'visible'], false))
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonFragment(['title' => $pendingDomain->hostname]);
    }

    public function test_platform_search_uses_named_ability_and_shares_effective_capability(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);
        $workspace = $administrator->currentWorkspace;
        $workspace->folders()->create(['user_id' => $administrator->id, 'name' => 'Needle workspace folder']);
        $account = User::factory()->create(['name' => 'Needle private account']);
        $this->actingAs($administrator)
            ->getJson(route('search.index', ['q' => 'Needle'], false))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'account')
            ->assertJsonMissing(['type' => 'folder']);

        Gate::define('admin.search', fn (User $user): bool => false);
        $this->actingAs($administrator)
            ->getJson(route('search.index', ['q' => 'Needle'], false))
            ->assertOk()
            ->assertJsonPath('results.0.type', 'folder')
            ->assertJsonMissing(['title' => 'Needle private account']);

        $this->withoutVite();
        $this->actingAs($administrator)
            ->get(route('links.index', [], false))
            ->assertInertia(fn (Assert $page) => $page->where('auth.canSearchPlatform', false));
    }

    public function test_search_and_list_filters_only_return_current_workspace_resources(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $this->addSearchableResources($workspace, $owner, 'shared');

        $outsider = User::factory()->create();
        $this->addSearchableResources($outsider->currentWorkspace, $outsider, 'private');

        $this->actingAs($member)
            ->getJson(route('search.index', ['q' => 'Shared Marker'], false))
            ->assertOk()
            ->assertJsonCount(5, 'results')
            ->assertJsonPath('results.0.type', 'link')
            ->assertJsonPath('results.0.title', 'Shared Marker shared short link')
            ->assertJsonPath('results.0.href', route('links.index', ['search' => 'Shared Marker'], false))
            ->assertJsonPath('results.1.type', 'folder')
            ->assertJsonPath('results.1.href', route('folders.index', ['search' => 'Shared Marker'], false))
            ->assertJsonPath('results.2.type', 'bio_page')
            ->assertJsonPath('results.3.type', 'link_page')
            ->assertJsonPath('results.4.type', 'qr_code')
            ->assertJsonMissing(['title' => 'Shared Marker private short link'])
            ->assertJsonMissing(['title' => 'Shared Marker private folder'])
            ->assertJsonMissing(['title' => 'Shared Marker private bio'])
            ->assertJsonMissing(['title' => 'Shared Marker private page'])
            ->assertDontSee('private.example')
            ->assertDontSee('not-returned-access-code')
            ->assertDontSee('destination_url');

        $this->withoutVite();
        $this->actingAs($member)
            ->get(route('links.index', ['search' => 'Shared Marker'], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/index', false)
                ->where('filters.search', 'Shared Marker')
                ->has('links.data', 1)
                ->where('links.data.0.title', 'Shared Marker shared short link'));

        $this->actingAs($member)
            ->get(route('folders.index', ['search' => 'Shared Marker'], false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('folders/index', false)
                ->where('filters.search', 'Shared Marker')
                ->has('folders.data', 1)
                ->where('folders.data.0.name', 'Shared Marker shared folder'));
    }

    public function test_global_search_treats_like_wildcards_and_escape_character_literally(): void
    {
        $user = User::factory()->create();
        $workspace = $user->currentWorkspace;
        $workspace->links()->create([
            'user_id' => $user->id,
            'title' => 'Contains \\%_ exactly',
            'slug' => 'contains-literal-markers',
            'destination_url' => 'https://safe.example/path',
            'is_active' => true,
        ]);
        $workspace->links()->create([
            'user_id' => $user->id,
            'title' => 'Contains %_ exactly',
            'slug' => 'contains-markers-without-escape',
            'destination_url' => 'https://safe.example/other',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('search.index', ['q' => '\\%_'], false))
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.title', 'Contains \\%_ exactly');
    }

    public function test_admin_global_search_returns_account_metadata_and_plans_by_name_or_code_only(): void
    {
        $administrator = User::factory()->create([
            'name' => 'Platform Administrator',
            'email' => 'admin-search@example.test',
            'is_admin' => true,
        ]);
        $account = User::factory()->create([
            'name' => 'Searchable Account Metadata',
            'email' => 'account-metadata@example.test',
        ]);
        $account->currentWorkspace->links()->create([
            'user_id' => $account->id,
            'title' => 'Private Content Needle',
            'slug' => 'private-content-needle',
            'destination_url' => 'https://destination-secret.example/path',
            'is_active' => true,
        ]);

        $pro = Plan::query()->where('code', 'pro')->sole();
        $pro->update(['name' => 'Growth Tier']);

        $this->actingAs($administrator)
            ->getJson(route('search.index', ['q' => 'Searchable Account'], false))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'account')
            ->assertJsonPath('results.0.title', 'Searchable Account Metadata')
            ->assertJsonMissing(['type' => 'link'])
            ->assertDontSee('destination-secret.example');
        $this->actingAs($administrator)
            ->getJson(route('search.index', ['q' => 'pro'], false))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.type', 'plan')
            ->assertJsonPath('results.0.title', 'Growth Tier');
        $this->actingAs($administrator)
            ->getJson(route('search.index', ['q' => 'Private Content Needle'], false))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $this->actingAs($account)
            ->getJson(route('search.index', ['q' => 'account-metadata@example.test'], false))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_global_search_requires_at_least_two_characters(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('search.index', ['q' => 'x'], false))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    private function addSearchableResources(Workspace $workspace, User $creator, string $suffix): void
    {
        $workspace->links()->create([
            'user_id' => $creator->id,
            'title' => "Shared Marker {$suffix} short link",
            'slug' => "shared-marker-{$suffix}",
            'destination_url' => "https://{$suffix}.example/hidden-destination",
            'access_code' => 'not-returned-access-code',
            'is_active' => true,
        ]);
        $workspace->folders()->create([
            'user_id' => $creator->id,
            'name' => "Shared Marker {$suffix} folder",
        ]);
        $workspace->bioPages()->create([
            'user_id' => $creator->id,
            'handle' => "shared-marker-{$suffix}-bio",
            'display_name' => "Shared Marker {$suffix} bio",
            'bio' => 'Search fixture',
            'is_published' => false,
        ]);
        $workspace->linkPages()->create([
            'user_id' => $creator->id,
            'name' => "Shared Marker {$suffix} page",
            'slug' => "shared-marker-{$suffix}-page",
            'title' => "Shared Marker {$suffix} page",
            'is_published' => false,
        ]);
    }
}
