<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Click;
use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_counts_active_workspace_resources_across_creators_only(): void
    {
        $viewer = User::factory()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::query()->create([
            'name' => 'Shared analytics workspace',
            'slug' => 'shared-analytics-'.$viewer->id,
            'owner_id' => $owner->id,
        ]);
        $workspace->members()->attach($owner, ['role' => 'owner']);
        $workspace->members()->attach($viewer, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($viewer, $workspace);

        $sharedLink = Link::factory()->for($owner)->create([
            'slug' => 'shared-analytics-link',
            'workspace_id' => $workspace->id,
        ]);
        $personalWorkspace = $viewer->personalWorkspace()->firstOrFail();
        $personalLink = Link::factory()->for($viewer)->create([
            'slug' => 'personal-analytics-link',
            'workspace_id' => $personalWorkspace->id,
        ]);
        Click::factory()->count(2)->for($sharedLink)->create(['occurred_at' => now('UTC')]);
        Click::factory()->for($personalLink)->create(['occurred_at' => now('UTC')]);
        AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'link_id' => $sharedLink->id,
            'event_name' => 'link.clicked',
            'occurred_at' => now('UTC'),
            'visitor_hash' => hash('sha256', 'shared-workspace-visitor'),
            'visitor_key_version' => 'test-v1',
            'referrer_host' => 'team.example',
            'browser' => 'Chrome',
            'platform' => 'Linux',
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)
            ->get('/dashboard/insights')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/index', false)
                ->where('analytics.totalClicks', 2)
                ->where('analytics.totalEvents', 1));
    }
}
