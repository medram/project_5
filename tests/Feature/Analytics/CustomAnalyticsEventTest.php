<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class CustomAnalyticsEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_and_admin_can_declare_custom_event_names(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'custom-events-owner-admin');
        $workspace->members()->attach($admin->id, ['role' => 'admin']);
        $this->activateWorkspace($owner, $workspace);
        $this->activateWorkspace($admin, $workspace);

        $this->actingAs($owner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'signup.completed'])
            ->assertRedirect();

        $this->assertDatabaseHas('analytics_event_definitions', [
            'workspace_id' => $workspace->id,
            'event_name' => 'signup.completed',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'trial.started'])
            ->assertRedirect();

        $this->assertDatabaseHas('analytics_event_definitions', [
            'workspace_id' => $workspace->id,
            'event_name' => 'trial.started',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($owner)
            ->get('/dashboard/insights/custom-events')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('analytics/custom-events')
                ->has('definitions', 2)
                ->where('definitions.0.event_name', 'signup.completed')
                ->where('definitions.1.event_name', 'trial.started')
            );
    }

    public function test_workspace_member_cannot_read_or_create_custom_event_definitions(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'custom-events-member-denial');
        $workspace->members()->attach($member->id, ['role' => 'member']);
        $this->activateWorkspace($member, $workspace);

        $this->actingAs($member)
            ->get('/dashboard/insights')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageCustomEvents', false)
            );

        $this->actingAs($member)
            ->get('/dashboard/insights/custom-events')
            ->assertForbidden();

        $this->actingAs($member)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'signup.completed'])
            ->assertForbidden();

        $this->assertDatabaseCount('analytics_event_definitions', 0);
    }

    public function test_duplicate_and_invalid_custom_event_names_are_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'custom-events-validation');
        $this->activateWorkspace($owner, $workspace);

        $this->actingAs($owner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'signup.completed'])
            ->assertRedirect();

        $this->actingAs($owner)
            ->from('/dashboard/insights/custom-events')
            ->post('/dashboard/insights/custom-events', ['event_name' => 'signup.completed'])
            ->assertSessionHasErrors('event_name');

        foreach ([
            'Signup.completed',
            'signup completed',
            'signup..completed',
            'link.clicked',
            str_repeat('a', 65),
        ] as $invalidName) {
            $this->actingAs($owner)
                ->from('/dashboard/insights/custom-events')
                ->post('/dashboard/insights/custom-events', ['event_name' => $invalidName])
                ->assertSessionHasErrors('event_name');
        }

        $this->assertDatabaseCount('analytics_event_definitions', 1);
    }

    public function test_owner_can_disable_a_custom_event_definition(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'custom-events-disable');
        $this->activateWorkspace($owner, $workspace);

        $this->actingAs($owner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'signup.completed'])
            ->assertRedirect();

        $definitionId = (int) DB::table('analytics_event_definitions')
            ->where('workspace_id', $workspace->id)
            ->where('event_name', 'signup.completed')
            ->value('id');

        $this->actingAs($owner)
            ->patch("/dashboard/insights/custom-events/{$definitionId}/disable")
            ->assertRedirect();

        $this->assertDatabaseHas('analytics_event_definitions', [
            'id' => $definitionId,
            'workspace_id' => $workspace->id,
            'event_name' => 'signup.completed',
            'is_active' => false,
        ]);
    }

    public function test_custom_event_definitions_are_isolated_between_workspaces(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstWorkspace = $this->teamWorkspace($firstOwner, 'custom-events-workspace-one');
        $secondWorkspace = $this->teamWorkspace($secondOwner, 'custom-events-workspace-two');
        $this->activateWorkspace($firstOwner, $firstWorkspace);
        $this->activateWorkspace($secondOwner, $secondWorkspace);

        $this->actingAs($firstOwner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'purchase.completed'])
            ->assertRedirect();
        $this->actingAs($secondOwner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'purchase.completed'])
            ->assertRedirect();

        $firstDefinitionId = (int) DB::table('analytics_event_definitions')
            ->where('workspace_id', $firstWorkspace->id)
            ->value('id');
        $secondDefinitionId = (int) DB::table('analytics_event_definitions')
            ->where('workspace_id', $secondWorkspace->id)
            ->value('id');

        $this->actingAs($firstOwner)
            ->get('/dashboard/insights/custom-events')
            ->assertInertia(fn (Assert $page) => $page
                ->has('definitions', 1)
                ->where('definitions.0.event_name', 'purchase.completed')
            );

        $this->actingAs($firstOwner)
            ->patch("/dashboard/insights/custom-events/{$secondDefinitionId}/disable")
            ->assertNotFound();

        $this->assertDatabaseHas('analytics_event_definitions', [
            'id' => $firstDefinitionId,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('analytics_event_definitions', [
            'id' => $secondDefinitionId,
            'is_active' => true,
        ]);
    }

    public function test_tracker_records_an_active_custom_event_using_normal_analytics_fields(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'custom-events-tracker');
        $this->activateWorkspace($owner, $workspace);
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->actingAs($owner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'subscription.started'])
            ->assertRedirect();

        $request = Request::create('/', 'GET');
        $request->cookies->set(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED);
        $event = app(AnalyticsTracker::class)->record(
            $request,
            'subscription.started',
            $owner,
            ['link' => $link],
        );

        $this->assertInstanceOf(AnalyticsEvent::class, $event);
        $this->assertSame('subscription.started', $event->event_name);
        $this->assertSame($workspace->id, $event->workspace_id);
        $this->assertNotNull($event->visitor_hash);
        $this->assertDatabaseHas('analytics_events', [
            'workspace_id' => $workspace->id,
            'link_id' => $link->id,
            'event_name' => 'subscription.started',
        ]);
    }

    public function test_tracker_rejects_undeclared_custom_event_names(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'custom-events-undeclared');
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $request = Request::create('/', 'GET');
        $request->cookies->set(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED);

        $this->expectException(InvalidArgumentException::class);

        app(AnalyticsTracker::class)->record(
            $request,
            'subscription.started',
            $owner,
            ['link' => $link],
        );
    }

    public function test_tracker_rejects_disabled_custom_event_names(): void
    {
        $owner = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'custom-events-disabled-ingestion');
        $this->activateWorkspace($owner, $workspace);
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->actingAs($owner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'subscription.started'])
            ->assertRedirect();

        $definitionId = (int) DB::table('analytics_event_definitions')
            ->where('workspace_id', $workspace->id)
            ->value('id');
        $this->actingAs($owner)
            ->patch("/dashboard/insights/custom-events/{$definitionId}/disable")
            ->assertRedirect();

        $request = Request::create('/', 'GET');
        $request->cookies->set(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED);

        $this->expectException(InvalidArgumentException::class);

        app(AnalyticsTracker::class)->record(
            $request,
            'subscription.started',
            $owner,
            ['link' => $link],
        );
    }

    public function test_tracker_does_not_accept_a_definition_from_another_workspace(): void
    {
        $resourceOwner = User::factory()->create();
        $definitionOwner = User::factory()->create();
        $resourceWorkspace = $this->teamWorkspace($resourceOwner, 'custom-events-resource-workspace');
        $definitionWorkspace = $this->teamWorkspace($definitionOwner, 'custom-events-definition-workspace');
        $this->activateWorkspace($definitionOwner, $definitionWorkspace);
        $link = Link::factory()->create([
            'user_id' => $resourceOwner->id,
            'workspace_id' => $resourceWorkspace->id,
        ]);

        $this->actingAs($definitionOwner)
            ->post('/dashboard/insights/custom-events', ['event_name' => 'subscription.started'])
            ->assertRedirect();

        $request = Request::create('/', 'GET');
        $request->cookies->set(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED);

        $this->expectException(InvalidArgumentException::class);

        app(AnalyticsTracker::class)->record(
            $request,
            'subscription.started',
            $resourceOwner,
            ['link' => $link],
        );
    }

    private function teamWorkspace(User $owner, string $slug): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Custom Analytics Team',
            'slug' => $slug,
            'personal_owner_user_id' => null,
            'owner_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Workspace::query()->findOrFail($workspaceId);
    }

    private function activateWorkspace(User $user, Workspace $workspace): void
    {
        app(WorkspaceContext::class)->activate($user, $workspace);
    }
}
