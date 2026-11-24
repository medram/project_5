<?php

namespace Tests\Feature\QrCodes;

use App\Enums\PlanMetric;
use App\Models\Link;
use App\Models\Plan;
use App\Models\QrCode;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\GeoIpCountryResolver;
use App\Services\Billing\WorkspaceUsage;
use App\Services\QrCodes\QrScanRecorder;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_campaign_list_includes_workspace_member_links_and_direct_campaigns_only(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $sharedLink = $workspace->links()->create([
            'user_id' => $owner->id, 'slug' => 'shared-qr-link',
            'destination_url' => 'https://example.com', 'is_active' => true,
        ]);
        $workspace->qrCodes()->create([
            'source' => 'campaign',
            'destination_url' => 'https://example.com/direct',
        ]);
        $foreignLink = Link::factory()->create(['slug' => 'foreign-qr-link']);
        $this->withoutVite();

        $this->actingAs($member)->get('/dashboard/qr-codes')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('campaigns', 2)
                ->where('campaigns.0.link.slug', 'shared-qr-link')
                ->where('campaigns.1.destination_url', 'https://example.com/direct'));
        $this->assertNotSame($sharedLink->workspace_id, $foreignLink->workspace_id);
    }

    public function test_index_only_includes_the_signed_in_owners_links(): void
    {
        $owner = User::factory()->create();
        $ownedLink = Link::factory()->for($owner)->create(['slug' => 'owned-qr-26']);
        Link::factory()->create(['slug' => 'other-qr-26']);

        $this->withoutVite();

        $this
            ->actingAs($owner)
            ->get('/dashboard/qr-codes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('qr-codes/index', false)
                ->has('campaigns', 1)
                ->where('campaigns.0.link.slug', 'owned-qr-26'),
            );
    }

    public function test_owner_can_render_an_svg_qr_code_for_a_link(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create(['slug' => 'svg-qr-26']);

        $this
            ->actingAs($owner)
            ->get("/dashboard/links/{$link->id}/qr-code.svg")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);
    }

    public function test_member_cannot_render_a_qr_code_outside_the_active_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Another workspace',
            'slug' => 'another-qr-'.$user->id,
            'owner_id' => $user->id,
        ]);
        $workspace->members()->attach($user, ['role' => 'owner']);
        $link = Link::factory()->for($user)->create([
            'slug' => 'inactive-workspace-qr',
            'workspace_id' => $workspace->id,
        ]);

        $this->actingAs($user)
            ->get("/dashboard/links/{$link->id}/qr-code.svg")
            ->assertNotFound();
    }

    public function test_non_owner_cannot_render_a_links_qr_code(): void
    {
        $link = Link::factory()->create(['slug' => 'private-qr-26']);

        $this
            ->actingAs(User::factory()->create())
            ->get("/dashboard/links/{$link->id}/qr-code.svg")
            ->assertForbidden();
    }

    public function test_workspace_can_create_direct_qr_campaigns_but_cannot_target_its_own_host(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/dashboard/qr-codes', [
            'title' => 'Restaurant flyer',
            'destination_url' => 'https://example.com/menu',
        ])->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();

        $qrCode = QrCode::query()->sole();
        $this->assertSame($owner->currentWorkspace->id, $qrCode->workspace_id);
        $this->assertSame('campaign', $qrCode->source);
        $this->assertSame('https://example.com/menu', $qrCode->destination_url);
        $this->assertNull($qrCode->link_id);

        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'title' => 'Self-target',
            'destination_url' => config('app.url').'/dashboard',
        ])->assertSessionHasErrors('destination_url');
        $this->assertDatabaseCount('qr_codes', 1);
    }

    public function test_public_direct_campaign_records_one_anonymous_scan_before_redirecting(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/menu',
        ]);

        $this->get("/q/{$qrCode->code}")
            ->assertRedirect('https://example.com/menu');

        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'scan_count' => 1]);
        $this->assertDatabaseCount('qr_scans', 1);
        $scan = DB::table('qr_scans')->where('qr_code_id', $qrCode->id)->sole();
        $this->assertNotNull($scan->scanned_at);
        $this->assertSame(
            ['id', 'qr_code_id', 'scanned_at', 'country_code', 'browser', 'platform', 'referrer_host'],
            Schema::getColumnListing('qr_scans'),
        );
    }

    public function test_public_qr_scan_records_coarse_request_dimensions_without_raw_identity(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/dimensions',
        ]);
        $this->app->instance(GeoIpCountryResolver::class, new class extends GeoIpCountryResolver
        {
            public function countryCode(string $ip): ?string
            {
                return 'US';
            }
        });

        $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36',
        )->withHeader('Referer', 'https://news.example/articles/qr')->get("/q/{$qrCode->code}")
            ->assertRedirect('https://example.com/dimensions');

        $scan = DB::table('qr_scans')->where('qr_code_id', $qrCode->id)->sole();
        $this->assertSame('US', $scan->country_code);
        $this->assertSame('Chrome', $scan->browser);
        $this->assertSame('Windows', $scan->platform);
        $this->assertSame('news.example', $scan->referrer_host);
        $this->assertFalse(Schema::hasColumn('qr_scans', 'ip_address'));
        $this->assertFalse(Schema::hasColumn('qr_scans', 'visitor_hash'));
    }

    public function test_qr_analytics_summarizes_scan_dimensions_and_devices(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/dimension-totals',
        ]);
        $foreignQr = QrCode::query()->create([
            'workspace_id' => User::factory()->create()->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.net/foreign-dimensions',
        ]);

        foreach ([
            ['country_code' => 'US', 'browser' => 'Chrome', 'platform' => 'Windows', 'referrer_host' => 'google.com'],
            ['country_code' => 'US', 'browser' => 'Chrome', 'platform' => 'Windows', 'referrer_host' => 'google.com'],
            ['country_code' => 'CA', 'browser' => 'Safari', 'platform' => 'iOS'],
            ['country_code' => 'DE', 'browser' => 'Firefox', 'platform' => 'Android', 'referrer_host' => 'news.example'],
            [],
        ] as $dimensions) {
            $qrCode->scans()->create(['scanned_at' => now(), ...$dimensions]);
        }
        $foreignQr->scans()->create([
            'scanned_at' => now(),
            'country_code' => 'FR',
            'browser' => 'Chrome',
            'platform' => 'Windows',
        ]);
        $this->withoutVite();

        $this->actingAs($owner)->get('/dashboard/qr-codes/insights?days=7')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.totalScans', 5)
                ->where('analytics.countryCount', 3)
                ->where('analytics.browserCount', 3)
                ->where('analytics.deviceCount', 2)
                ->has('analytics.topCountries', 3)
                ->where('analytics.topCountries.0.name', 'US')
                ->where('analytics.topCountries.0.count', 2)
                ->where('analytics.topBrowsers.0.name', 'Chrome')
                ->where('analytics.topBrowsers.0.count', 2)
                ->where('analytics.topPlatforms.0.name', 'Windows')
                ->where('analytics.topPlatforms.0.count', 2)
                ->where('analytics.topDevices.0.name', 'Desktop')
                ->where('analytics.topDevices.0.count', 2)
                ->where('analytics.topReferrers.0.name', 'google.com')
                ->where('analytics.topReferrers.0.count', 2));

        $this->get("/dashboard/qr-codes/{$qrCode->id}/insights?days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.totalScans', 5)
                ->where('analytics.topDevices.1.name', 'Mobile')
                ->where('analytics.topDevices.1.count', 2));
    }

    public function test_linked_campaign_scan_redirects_to_the_canonical_link_without_recording_a_link_click(): void
    {
        $link = Link::factory()->create(['slug' => 'qr-canonical-target']);
        $qrCode = $link->qrCode()->firstOrFail();

        $this->get("/q/{$qrCode->code}")
            ->assertRedirect(rtrim((string) config('app.url'), '/').'/qr-canonical-target');

        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'scan_count' => 1]);
        $this->assertDatabaseCount('qr_scans', 1);
        $this->assertDatabaseCount('clicks', 0);
    }

    public function test_linked_qr_records_scan_attempt_when_short_link_is_inactive_without_creating_a_click(): void
    {
        $link = Link::factory()->create([
            'slug' => 'qr-inactive-target',
            'is_active' => false,
        ]);
        $qrCode = $link->qrCode()->firstOrFail();

        $this->followingRedirects()
            ->get("/q/{$qrCode->code}")
            ->assertNotFound();

        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'scan_count' => 1]);
        $this->assertDatabaseCount('qr_scans', 1);
        $this->assertDatabaseCount('clicks', 0);
    }

    public function test_qr_unlock_is_campaign_scoped_and_the_last_allowed_scan_is_atomic(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/menu',
            'title' => 'Dinner menu',
            'share_title' => 'Scan our menu',
            'access_code' => Hash::make('secret-code'),
            'max_scans' => 1,
        ]);
        $otherQrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/other',
            'access_code' => Hash::make('secret-code'),
        ]);
        $this->withoutVite();

        $this->get("/q/{$qrCode->code}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('qr-codes/unlock', false)
                ->where('title', 'Scan our menu'));
        $this->post("/q/{$qrCode->code}/unlock", ['access_code' => 'wrong'])
            ->assertSessionHasErrors('access_code');
        $this->assertDatabaseCount('qr_scans', 0);

        $this->post("/q/{$qrCode->code}/unlock", ['access_code' => 'secret-code'])
            ->assertRedirect("/q/{$qrCode->code}");
        $this->get("/q/{$otherQrCode->code}")
            ->assertInertia(fn (Assert $page) => $page->component('qr-codes/unlock', false));
        $this->assertDatabaseCount('qr_scans', 0);

        $this->get("/q/{$qrCode->code}")->assertRedirect('https://example.com/menu');
        $this->get("/q/{$qrCode->code}")->assertNotFound();
        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'scan_count' => 1]);
        $this->assertDatabaseCount('qr_scans', 1);
    }

    public function test_scan_rechecks_protected_access_after_campaign_changes(): void
    {
        $owner = User::factory()->create();
        $qrCode = $owner->currentWorkspace->qrCodes()->create([
            'source' => 'campaign',
            'destination_url' => 'https://example.com/access-race',
            'access_code' => Hash::make('old-code'),
            'access_code_version' => 1,
        ]);

        $this->post("/q/{$qrCode->code}/unlock", ['access_code' => 'old-code'])
            ->assertRedirect("/q/{$qrCode->code}");

        $recorder = app(QrScanRecorder::class);
        app()->instance(QrScanRecorder::class, new class($recorder) extends QrScanRecorder
        {
            public function __construct(private readonly QrScanRecorder $recorder) {}

            public function record(Request $request, QrCode $qrCode): ?string
            {
                DB::table('qr_codes')->where('id', $qrCode->id)->update([
                    'access_code' => Hash::make('new-code'),
                    'access_code_version' => 2,
                ]);

                return $this->recorder->record($request, $qrCode);
            }
        });

        $this->get("/q/{$qrCode->code}")->assertNotFound();
        $this->assertDatabaseHas('qr_codes', [
            'id' => $qrCode->id,
            'access_code_version' => 2,
            'scan_count' => 0,
        ]);
        $this->assertDatabaseCount('qr_scans', 0);
    }

    public function test_disabled_and_future_qr_campaigns_fail_without_scan_records(): void
    {
        $owner = User::factory()->create();
        $disabled = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/disabled',
            'is_active' => false,
        ]);
        $scheduled = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/scheduled',
            'available_from' => now()->addDay(),
        ]);

        $expired = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/expired',
            'expires_at' => now()->subSecond(),
        ]);
        $this->get("/q/{$disabled->code}")->assertNotFound();
        $this->get("/q/{$scheduled->code}")->assertNotFound();
        $this->get("/q/{$expired->code}")->assertNotFound();
        $this->assertDatabaseCount('qr_scans', 0);
        $this->assertDatabaseHas('qr_codes', ['id' => $disabled->id, 'scan_count' => 0]);
        $this->assertDatabaseHas('qr_codes', ['id' => $scheduled->id, 'scan_count' => 0]);
        $this->assertDatabaseHas('qr_codes', ['id' => $expired->id, 'scan_count' => 0]);
    }

    public function test_qr_unlock_throttle_is_limited_by_campaign_and_ip(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/throttle-one',
            'access_code' => Hash::make('correct-code'),
        ]);
        $otherQr = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/throttle-two',
            'access_code' => Hash::make('correct-code'),
        ]);
        $firstIp = '198.51.100.10';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $firstIp])
                ->post("/q/{$qrCode->code}/unlock", ['access_code' => 'wrong-code'])
                ->assertSessionHasErrors('access_code');
        }

        $this->withServerVariables(['REMOTE_ADDR' => $firstIp])
            ->post("/q/{$qrCode->code}/unlock", ['access_code' => 'wrong-code'])
            ->assertTooManyRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11'])
            ->post("/q/{$qrCode->code}/unlock", ['access_code' => 'wrong-code'])
            ->assertSessionHasErrors('access_code');
        $this->withServerVariables(['REMOTE_ADDR' => $firstIp])
            ->post("/q/{$otherQr->code}/unlock", ['access_code' => 'wrong-code'])
            ->assertSessionHasErrors('access_code');

        $this->assertDatabaseCount('qr_scans', 0);
    }

    public function test_owned_link_campaigns_do_not_consume_direct_qr_allowance(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        Plan::query()->where('code', 'free')->update(['qr_campaign_limit' => 1]);
        $link = Link::factory()->for($owner)->create(['slug' => 'campaign-target-link']);

        $this->actingAs($owner)->post('/dashboard/qr-codes', [
            'title' => 'Owned link poster',
            'link_id' => $link->id,
        ])->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();

        $campaign = $workspace->qrCodes()->where('source', 'campaign')->sole();
        $this->assertSame($link->id, $campaign->link_id);
        $this->assertNull($campaign->destination_url);
        $usage = app(WorkspaceUsage::class)->metricFor($workspace, PlanMetric::QrCampaigns);
        $this->assertSame(['used' => 0, 'limit' => 1], array_intersect_key($usage, ['used' => true, 'limit' => true]));

        $this->post('/dashboard/qr-codes', ['destination_url' => 'https://example.com/one'])
            ->assertSessionHasNoErrors();
        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/two',
        ])->assertSessionHasErrors('plan');
        $this->assertSame(1, $workspace->qrCodes()->where('source', 'campaign')->whereNotNull('destination_url')->count());
    }

    public function test_changing_an_owned_link_campaign_to_a_direct_target_reserves_allowance(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        Plan::query()->where('code', 'free')->update(['qr_campaign_limit' => 1]);
        $link = Link::factory()->for($owner)->create();
        $campaign = $workspace->qrCodes()->create([
            'source' => 'campaign',
            'link_id' => $link->id,
        ]);
        $this->actingAs($owner)->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/limited',
        ])->assertSessionHasNoErrors();

        $this->from('/dashboard/qr-codes')->patch("/dashboard/qr-codes/{$campaign->id}", [
            'link_id' => null,
            'destination_url' => 'https://example.com/over-limit',
        ])->assertSessionHasErrors('plan');
        $this->assertDatabaseHas('qr_codes', [
            'id' => $campaign->id,
            'link_id' => $link->id,
            'destination_url' => null,
        ]);

        Plan::query()->where('code', 'free')->update(['qr_campaign_limit' => 2]);
        $this->patch("/dashboard/qr-codes/{$campaign->id}", [
            'link_id' => null,
            'destination_url' => 'https://example.com/allowed',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('qr_codes', [
            'id' => $campaign->id,
            'link_id' => null,
            'destination_url' => 'https://example.com/allowed',
        ]);
        $usage = app(WorkspaceUsage::class)->metricFor($workspace, PlanMetric::QrCampaigns);
        $this->assertSame(['used' => 2, 'limit' => 2], array_intersect_key($usage, ['used' => true, 'limit' => true]));
    }

    public function test_updating_an_access_code_invalidates_the_previous_qr_unlock(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/secure',
            'access_code' => Hash::make('old-code'),
        ]);
        $this->withoutVite();
        $this->post("/q/{$qrCode->code}/unlock", ['access_code' => 'old-code'])
            ->assertRedirect("/q/{$qrCode->code}");

        $this->actingAs($owner)->patch("/dashboard/qr-codes/{$qrCode->id}", [
            'access_code' => 'new-code',
            'share_title' => 'New protected title',
            'foreground_color' => '#123456',
            'size' => 512,
        ])->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();
        $qrCode->refresh();
        $this->assertSame(2, $qrCode->access_code_version);
        $this->assertTrue(Hash::check('new-code', $qrCode->access_code));

        $this->get("/q/{$qrCode->code}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('qr-codes/unlock', false)
                ->where('title', 'New protected title'));
        $this->post("/q/{$qrCode->code}/unlock", ['access_code' => 'old-code'])
            ->assertSessionHasErrors('access_code');
        $this->assertDatabaseCount('qr_scans', 0);
    }

    public function test_protected_qr_unlock_does_not_expose_the_internal_campaign_name(): void
    {
        $owner = User::factory()->create();
        $qrCode = $owner->currentWorkspace->qrCodes()->create([
            'source' => 'campaign',
            'title' => 'Internal launch plan',
            'destination_url' => 'https://example.com/private',
            'access_code' => Hash::make('secret-code'),
        ]);
        $this->withoutVite();

        $this->get("/q/{$qrCode->code}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('qr-codes/unlock', false)
                ->where('title', null))
            ->assertDontSee('Internal launch plan');
    }

    public function test_form_encoded_access_code_removal_clears_the_gate_and_invalidates_unlocks(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/no-longer-locked',
            'access_code' => Hash::make('old-code'),
        ]);
        $this->post("/q/{$qrCode->code}/unlock", ['access_code' => 'old-code'])
            ->assertRedirect("/q/{$qrCode->code}");

        $this->actingAs($owner)->patch("/dashboard/qr-codes/{$qrCode->id}", [
            'remove_access_code' => '1',
        ])->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();
        $qrCode->refresh();
        $this->assertNull($qrCode->access_code);
        $this->assertSame(2, $qrCode->access_code_version);

        $this->get("/q/{$qrCode->code}")->assertRedirect('https://example.com/no-longer-locked');
        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'scan_count' => 1]);
    }

    public function test_qr_csv_export_is_stable_workspace_scoped_and_excludes_private_fields(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'title' => 'Summer menu',
            'destination_url' => 'https://example.com/menu',
            'access_code' => Hash::make('never-export-this'),
            'logo_path' => "qr-logos/{$owner->currentWorkspace->id}/private-logo.png",
        ]);
        $foreign = QrCode::query()->create([
            'workspace_id' => User::factory()->create()->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.net/foreign',
        ]);

        $first = $this->actingAs($owner)->get('/dashboard/qr-codes/export')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="qr-campaigns.csv"')
            ->getContent();
        $second = $this->get('/dashboard/qr-codes/export')->getContent();

        $this->assertSame($first, $second);
        $this->assertStringContainsString($qrCode->code, $first);
        $this->assertStringContainsString('https://example.com/menu', $first);
        $this->assertStringNotContainsString($foreign->code, $first);
        $this->assertStringNotContainsString('never-export-this', $first);
        $this->assertStringNotContainsString('qr-logos/', $first);
    }

    public function test_qr_csv_export_sanitizes_formula_titles_and_keeps_orphaned_campaigns(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $unsafeTitles = [" \t=1+1", '+SUM(1,2)', '-1+1', '@SUM(1,2)'];

        foreach ($unsafeTitles as $index => $title) {
            $workspace->qrCodes()->create([
                'source' => 'campaign',
                'title' => $title,
                'destination_url' => "https://example.com/formula-{$index}",
            ]);
        }

        $orphanedCampaign = $workspace->qrCodes()->create([
            'source' => 'linked',
            'title' => 'Deleted link history',
        ]);

        $response = $this->actingAs($owner)->get('/dashboard/qr-codes/export')->assertOk();
        $rows = array_map(
            fn (string $line): array => str_getcsv($line),
            array_slice(explode("\r\n", trim($response->getContent())), 1),
        );

        foreach ($unsafeTitles as $title) {
            $this->assertContains("'".$title, array_column($rows, 0));
        }

        $orphanRow = collect($rows)->first(fn (array $row): bool => $row[1] === $orphanedCampaign->code);
        $this->assertNotNull($orphanRow);
        $this->assertSame('', $orphanRow[3]);
    }

    public function test_qr_csv_export_keeps_rows_when_a_direct_target_becomes_first_party(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $invalid = $workspace->qrCodes()->create([
            'source' => 'campaign',
            'destination_url' => 'https://claimed.example.test/menu',
        ]);
        $valid = $workspace->qrCodes()->create([
            'source' => 'campaign',
            'destination_url' => 'https://external.example.test/menu',
        ]);
        $now = now();
        DB::table('custom_domains')->insert([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'hostname' => 'claimed.example.test',
            'verification_token' => str_repeat('a', 40),
            'status' => 'pending_dns',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->actingAs($owner)->get('/dashboard/qr-codes/export')->assertOk();
        $rows = array_map(
            fn (string $line): array => str_getcsv($line),
            array_slice(explode("\r\n", trim($response->getContent())), 1),
        );
        $invalidRow = collect($rows)->first(fn (array $row): bool => $row[1] === $invalid->code);
        $validRow = collect($rows)->first(fn (array $row): bool => $row[1] === $valid->code);

        $this->assertNotNull($invalidRow);
        $this->assertSame('', $invalidRow[3]);
        $this->assertNotNull($validRow);
        $this->assertSame('https://external.example.test/menu', $validRow[3]);
    }

    public function test_authenticated_user_can_preview_unsaved_qr_styles_without_persisting_campaign(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get('/dashboard/qr-codes/draft-preview.svg?foreground_color=%23123456&background_color=%23FFFFFF&error_correction=high&margin=5&size=384')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);

        $this->assertDatabaseCount('qr_codes', 0);
    }

    public function test_qr_analytics_uses_only_workspace_scan_rows(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/analytics',
            'scan_count' => 99,
        ]);
        $foreignQr = QrCode::query()->create([
            'workspace_id' => User::factory()->create()->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.net/foreign',
        ]);
        $qrCode->scans()->create(['scanned_at' => now()]);
        $qrCode->scans()->create(['scanned_at' => now()->subDays(3)]);
        $foreignQr->scans()->create(['scanned_at' => now()]);
        $this->withoutVite();

        $this->actingAs($owner)->get('/dashboard/qr-codes/insights?days=7')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('qr-codes/insights', false)
                ->where('analytics.totalScans', 2)
                ->has('analytics.daily', 7));
        $this->get("/dashboard/qr-codes/{$qrCode->id}/insights?days=7")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'campaign')
                ->where('analytics.totalScans', 2));
    }

    public function test_campaign_insights_include_a_scoped_link_target(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create([
            'slug' => 'qr-insights-target',
            'title' => 'Insights target',
        ]);
        $qrCode = $link->qrCode()->firstOrFail();
        $this->withoutVite();

        $this->actingAs($owner)
            ->get("/dashboard/qr-codes/{$qrCode->id}/insights")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('qrCode.link.slug', 'qr-insights-target')
                ->where('qrCode.link.title', 'Insights target'));
    }

    public function test_qr_formats_return_real_images_with_stable_download_names(): void
    {
        $owner = User::factory()->create();
        $qrCode = QrCode::query()->create([
            'workspace_id' => $owner->currentWorkspace->id,
            'source' => 'campaign',
            'destination_url' => 'https://example.com/formats',
        ]);
        $this->actingAs($owner);

        $signatures = [
            'svg' => ['image/svg+xml', '<?xml'],
            'png' => ['image/png', "\x89PNG\r\n\x1a\n"],
            'jpg' => ['image/jpeg', "\xFF\xD8\xFF"],
            'webp' => ['image/webp', 'RIFF'],
        ];

        foreach ($signatures as $format => [$contentType, $signature]) {
            $response = $this->get("/dashboard/qr-codes/{$qrCode->id}/download/{$format}")
                ->assertOk()
                ->assertHeader('Content-Type', $contentType)
                ->assertHeader('Content-Disposition', "attachment; filename=\"qr-{$qrCode->code}.{$format}\"");
            $content = $response->getContent();
            $this->assertStringStartsWith($signature, $content);

            if ($format !== 'svg') {
                $this->assertNotFalse(getimagesizefromstring($content));
            }
            if ($format === 'webp') {
                $this->assertSame('WEBP', substr($content, 8, 4));
            }
        }

        $this->get("/dashboard/qr-codes/{$qrCode->id}/download/pdf")->assertNotFound();
    }

    public function test_qr_logos_are_private_scoped_and_deleted_when_replaced_or_removed(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/logo',
            'logo' => UploadedFile::fake()->image('brand.png', 96, 96),
            'logo_alt' => 'Blue mountain mark',
        ])->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();
        $qrCode = QrCode::query()->sole();
        $firstPath = $qrCode->logo_path;
        $this->assertStringStartsWith("qr-logos/{$owner->currentWorkspace->id}/", $firstPath);
        Storage::disk('local')->assertExists($firstPath);
        $this->assertArrayNotHasKey('logo_path', $qrCode->toArray());
        $this->assertArrayNotHasKey('access_code', $qrCode->toArray());

        $this->get("/dashboard/qr-codes/{$qrCode->id}/preview.svg")
            ->assertOk()
            ->assertSee('<image', false);

        $logoFormats = [
            'svg' => ['image/svg+xml', '<?xml'],
            'png' => ['image/png', "\x89PNG\r\n\x1a\n"],
            'jpg' => ['image/jpeg', "\xFF\xD8\xFF"],
            'webp' => ['image/webp', 'RIFF'],
        ];
        foreach ($logoFormats as $format => [$contentType, $signature]) {
            $content = $this->get("/dashboard/qr-codes/{$qrCode->id}/download/{$format}")
                ->assertOk()
                ->assertHeader('Content-Type', $contentType)
                ->getContent();
            $this->assertStringStartsWith($signature, $content);
            if ($format === 'svg') {
                $this->assertStringContainsString('<image', $content);
            } else {
                $this->assertNotFalse(getimagesizefromstring($content));
            }
        }

        $this->patch("/dashboard/qr-codes/{$qrCode->id}", [
            'logo' => UploadedFile::fake()->image('replacement.png', 80, 80),
        ])->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();
        $qrCode->refresh();
        $secondPath = $qrCode->logo_path;
        $this->assertNotSame($firstPath, $secondPath);
        $this->assertFalse(Storage::disk('local')->exists($firstPath));
        Storage::disk('local')->assertExists($secondPath);

        $this->patch("/dashboard/qr-codes/{$qrCode->id}", ['clear_logo' => '1'])
            ->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();
        $this->assertFalse(Storage::disk('local')->exists($secondPath));
    }

    public function test_invalid_logo_upload_is_rejected_without_persisting_a_campaign_or_file(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();

        $this->actingAs($owner)->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/invalid-logo',
            'logo' => UploadedFile::fake()->create('brand.gif', 32, 'image/gif'),
        ])->assertSessionHasErrors('logo');

        $this->assertSame(0, QrCode::query()->where('source', 'campaign')->count());
        $this->assertSame([], Storage::disk('local')->allFiles('qr-logos'));
    }

    public function test_qr_quota_failure_removes_a_logo_uploaded_before_campaign_persistence(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        Plan::query()->where('code', 'free')->update(['qr_campaign_limit' => 1]);
        $owner->currentWorkspace->qrCodes()->create([
            'source' => 'campaign',
            'destination_url' => 'https://example.com/already-counted',
        ]);

        $this->actingAs($owner)->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/quota',
            'logo' => UploadedFile::fake()->image('quota-logo.png', 64, 64),
        ])->assertSessionHasErrors('plan');

        $this->assertSame([], Storage::disk('local')->allFiles('qr-logos'));
        $this->assertSame(1, $owner->currentWorkspace->qrCodes()
            ->where('source', 'campaign')
            ->whereNotNull('destination_url')
            ->count());
    }

    public function test_campaign_targets_must_be_exactly_one_valid_workspace_target(): void
    {
        $owner = User::factory()->create();
        $foreignLink = Link::factory()->create();
        $this->actingAs($owner);

        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [])
            ->assertSessionHasErrors(['destination_url', 'link_id']);
        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com',
            'link_id' => Link::factory()->for($owner)->create()->id,
        ])->assertSessionHasErrors('destination_url');
        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('destination_url');
        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com',
            'link_id' => $foreignLink->id,
        ])->assertSessionHasErrors('link_id');
        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com',
            'foreground_color' => 'red',
        ])->assertSessionHasErrors('foreground_color');

        $this->assertSame(0, QrCode::query()->where('source', 'campaign')->count());
    }

    public function test_foreign_short_link_targets_are_not_serialized_and_fail_closed(): void
    {
        $owner = User::factory()->create();
        $foreignLink = Link::factory()->create(['slug' => 'foreign-qr-target']);
        $qrCode = $owner->currentWorkspace->qrCodes()->create([
            'source' => 'campaign',
            'link_id' => $foreignLink->id,
        ]);
        $this->withoutVite();

        $this->actingAs($owner)->get('/dashboard/qr-codes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('campaigns.0.link', null));
        $this->get("/q/{$qrCode->code}")->assertNotFound();
        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'scan_count' => 0]);
        $this->assertDatabaseCount('qr_scans', 0);
    }

    public function test_campaign_mutations_assets_and_insights_are_workspace_scoped(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $qrCode = $foreign->currentWorkspace->qrCodes()->create([
            'source' => 'campaign',
            'destination_url' => 'https://example.com/foreign',
        ]);
        $this->actingAs($owner);

        $this->get("/dashboard/qr-codes/{$qrCode->id}/preview.svg")->assertNotFound();
        $this->get("/dashboard/qr-codes/{$qrCode->id}/download/png")->assertNotFound();
        $this->get("/dashboard/qr-codes/{$qrCode->id}/insights")->assertNotFound();
        $this->patch("/dashboard/qr-codes/{$qrCode->id}", ['title' => 'forged'])->assertNotFound();
        $this->delete("/dashboard/qr-codes/{$qrCode->id}")->assertNotFound();

        $this->assertDatabaseHas('qr_codes', [
            'id' => $qrCode->id,
            'workspace_id' => $foreign->currentWorkspace->id,
            'title' => null,
        ]);
        $foreignLink = Link::factory()->for($foreign)->create();
        $foreignLinkedQrCode = $foreign->currentWorkspace->qrCodes()->create([
            'source' => 'campaign',
            'link_id' => $foreignLink->id,
        ]);
        $this->patch("/dashboard/qr-codes/{$foreignLinkedQrCode->id}", [
            'destination_url' => 'https://example.com/probe',
        ])->assertNotFound();

    }

    public function test_workspace_owners_cannot_delete_the_linked_qr_of_their_short_link(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create([
            'workspace_id' => $owner->currentWorkspace->id,
        ]);
        $linked = $link->qrCode()->sole();

        $this->actingAs($owner)
            ->delete("/dashboard/qr-codes/{$linked->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('qr_codes', ['id' => $linked->id]);
        $this->assertDatabaseHas('links', ['id' => $link->id]);
    }

    public function test_client_supplied_logo_paths_are_ignored(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();

        $this->actingAs($owner)->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/no-path',
            'logo_path' => "qr-logos/{$foreign->currentWorkspace->id}/private.png",
        ])->assertRedirect('/dashboard/qr-codes')->assertSessionHasNoErrors();

        $this->assertNull(QrCode::query()->sole()->logo_path);
    }

    public function test_failed_scan_insert_rolls_back_the_counter_increment(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The rollback trigger is SQLite-specific.');
        }

        $owner = User::factory()->create();
        $qrCode = $owner->currentWorkspace->qrCodes()->create([
            'source' => 'campaign',
            'destination_url' => 'https://example.com/rollback',
        ]);
        DB::statement("CREATE TRIGGER fail_qr_scan_insert BEFORE INSERT ON qr_scans BEGIN SELECT RAISE(ABORT, 'test failure'); END");

        try {
            $this->get("/q/{$qrCode->code}")->assertInternalServerError();
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_qr_scan_insert');
        }

        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'scan_count' => 0]);
        $this->assertDatabaseCount('qr_scans', 0);
    }

    public function test_link_relation_excludes_user_campaigns_that_target_the_same_link(): void
    {
        $link = Link::factory()->create();
        $linkedQr = $link->qrCode()->firstOrFail();
        $campaign = $link->workspace->qrCodes()->create([
            'source' => 'campaign',
            'link_id' => $link->id,
        ]);

        $this->assertFalse($link->qrCode()->whereKey($campaign->id)->exists());
        $this->assertSame($linkedQr->id, $link->qrCode()->firstOrFail()->id);
    }

    public function test_each_link_has_one_unmetered_qr_record_and_no_historical_scan(): void
    {
        $link = Link::factory()->create();

        $this->assertDatabaseHas('qr_codes', [
            'workspace_id' => $link->workspace_id,
            'link_id' => $link->id,
            'source' => 'linked',
            'scan_count' => 0,
        ]);
        $this->assertDatabaseCount('qr_codes', 1);
        $this->assertDatabaseCount('qr_scans', 0);
    }

    public function test_deleting_a_short_link_removes_its_derived_qr_but_preserves_campaign_history(): void
    {
        $link = Link::factory()->create(['slug' => 'qr-delete-target']);
        $linkedQr = $link->qrCode()->firstOrFail();
        $campaign = $link->workspace->qrCodes()->create([
            'source' => 'campaign',
            'link_id' => $link->id,
            'title' => 'Independent campaign',
        ]);
        $this->get("/q/{$campaign->code}")
            ->assertRedirect(rtrim((string) config('app.url'), '/').'/qr-delete-target');

        $link->delete();

        $this->assertDatabaseMissing('qr_codes', ['id' => $linkedQr->id]);
        $this->assertDatabaseHas('qr_codes', [
            'id' => $campaign->id,
            'link_id' => null,
            'scan_count' => 1,
        ]);
        $this->get("/q/{$campaign->code}")->assertNotFound();
        $this->assertDatabaseCount('qr_scans', 1);
    }

    public function test_qr_migration_backfills_existing_links_without_changing_click_history(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The migration reset is isolated to SQLite tests.');
        }

        $owner = User::factory()->create();
        $links = collect([
            Link::factory()->for($owner)->create([
                'slug' => 'preexisting-qr-one',
                'title' => 'Preexisting campaign one',
            ]),
            Link::factory()->for($owner)->create([
                'slug' => 'preexisting-qr-two',
                'title' => 'Preexisting campaign two',
            ]),
        ]);
        foreach ($links as $link) {
            DB::table('clicks')->insert([
                'link_id' => $link->id,
                'occurred_at' => now()->subDay(),
            ]);
        }
        $fields = ['id', 'workspace_id', 'user_id', 'slug', 'title', 'destination_url', 'is_active'];
        $before = DB::table('links')->orderBy('id')->get()->map(
            fn (object $link): array => collect($link)->only($fields)->all(),
        )->all();
        $clickCount = DB::table('clicks')->count();
        $migration = require database_path('migrations/2026_10_22_220000_create_qr_campaign_tables.php');

        $migration->down();
        $migration->up();

        $after = DB::table('links')->orderBy('id')->get()->map(
            fn (object $link): array => collect($link)->only($fields)->all(),
        )->all();
        $this->assertSame($before, $after);
        $this->assertSame($clickCount, DB::table('clicks')->count());
        $this->assertSame(2, DB::table('qr_codes')->count());
        $this->assertSame(0, DB::table('qr_scans')->count());
        foreach ($links as $link) {
            $this->assertDatabaseHas('qr_codes', [
                'workspace_id' => $link->workspace_id,
                'link_id' => $link->id,
                'linked_qr_link_id' => $link->id,
                'source' => 'linked',
                'scan_count' => 0,
            ]);
        }
    }
}
