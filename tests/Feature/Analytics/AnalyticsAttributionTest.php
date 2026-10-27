<?php

namespace Tests\Feature\Analytics;

use App\Jobs\DeliverWebhook;
use App\Models\AnalyticsEvent;
use App\Models\BioLink;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use App\Services\Analytics\TrackingConsent;
use App\Services\Webhooks\WebhookPayload;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Throwable;

class AnalyticsAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_link_clicks_keep_recording_after_the_creator_deletes_their_account(): void
    {
        [$workspace, $member] = $this->teamWorkspaceWithMember();
        $link = Link::factory()->for($member)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'retained-short-link',
            'destination_url' => 'https://example.com/retained',
        ]);

        $this->deleteAccount($member);

        $reports = new \ArrayObject();
        $this->captureReportedThrowables($reports);

        $this->get('/retained-short-link')
            ->assertRedirect('https://example.com/retained');

        $this->assertDatabaseCount('analytics_events', 0);

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/retained-short-link')
            ->assertRedirect('https://example.com/retained');

        $this->assertDatabaseCount('analytics_events', 1);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link.clicked',
            'link_id' => $link->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseCount('clicks', 2);
        self::assertCount(0, $reports, 'Consented delivery after creator deletion must not report errors.');
    }

    public function test_link_page_visits_keep_recording_after_the_creator_deletes_their_account(): void
    {
        [$workspace, $member] = $this->teamWorkspaceWithMember();
        $page = LinkPage::factory()->for($member)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'retained-campaign',
            'is_published' => true,
        ]);
        $item = $page->items()->create([
            'title' => 'Start here',
            'destination_url' => 'https://example.com/retained-destination',
            'position' => 0,
            'is_active' => true,
        ]);

        $this->deleteAccount($member);

        $reports = new \ArrayObject();
        $this->captureReportedThrowables($reports);
        $this->withoutVite();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/pages/retained-campaign')
            ->assertOk();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/pages/retained-campaign/items/{$item->id}")
            ->assertRedirect('https://example.com/retained-destination');

        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link_page.view',
            'link_page_id' => $page->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link_page.clicked',
            'link_page_id' => $page->id,
            'link_page_item_id' => $item->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        self::assertCount(0, $reports, 'Consented delivery after creator deletion must not report errors.');
    }

    public function test_overlay_visits_keep_recording_after_the_creator_deletes_their_account(): void
    {
        [$workspace, $member] = $this->teamWorkspaceWithMember();
        $overlay = LinkOverlay::factory()->create([
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);
        $link = Link::factory()->for($member)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'retained-overlay',
            'destination_url' => 'https://example.com/retained-final',
            'link_overlay_id' => $overlay->id,
        ]);

        $this->deleteAccount($member);

        $reports = new \ArrayObject();
        $this->captureReportedThrowables($reports);
        $this->withoutVite();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/retained-overlay')
            ->assertOk();

        $this->assertDatabaseCount('clicks', 0);

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->post('/retained-overlay/continue')
            ->assertRedirect('https://example.com/retained-final');

        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'overlay.view',
            'link_id' => $link->id,
            'link_overlay_id' => $overlay->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'link.clicked',
            'link_id' => $link->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseCount('clicks', 1);
        self::assertCount(0, $reports, 'Consented delivery after creator deletion must not report errors.');
    }

    public function test_bio_page_visits_keep_recording_after_the_creator_deletes_their_account(): void
    {
        [$workspace, $member] = $this->teamWorkspaceWithMember();
        $page = BioPage::factory()->for($member)->create([
            'workspace_id' => $workspace->id,
            'handle' => 'retained-bio',
            'is_published' => true,
        ]);
        $bioLink = BioLink::factory()->for($page)->create([
            'title' => 'Store',
            'destination_url' => 'https://example.com/retained-store',
        ]);

        $this->deleteAccount($member);

        $reports = new \ArrayObject();
        $this->captureReportedThrowables($reports);
        $this->withoutVite();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/bio/retained-bio')
            ->assertOk();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get("/bio/retained-bio/links/{$bioLink->id}")
            ->assertRedirect('https://example.com/retained-store');

        $this->assertDatabaseCount('analytics_events', 2);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'bio_page.view',
            'bio_page_id' => $page->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('analytics_events', [
            'event_name' => 'bio_page.clicked',
            'bio_page_id' => $page->id,
            'bio_link_id' => $bioLink->id,
            'workspace_id' => $workspace->id,
            'user_id' => null,
        ]);
        self::assertCount(0, $reports, 'Consented delivery after creator deletion must not report errors.');
    }

    public function test_webhook_deliveries_are_created_for_events_recorded_after_the_creator_deletes_their_account(): void
    {
        [$workspace, $member] = $this->teamWorkspaceWithMember();
        $link = Link::factory()->for($member)->create([
            'workspace_id' => $workspace->id,
            'slug' => 'retained-hook-link',
            'destination_url' => 'https://example.com/retained-hook',
        ]);
        $webhookId = DB::table('webhooks')->insertGetId([
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
            'name' => 'Retained webhook',
            'url' => 'https://hooks.example.test/events',
            'secret' => 'encrypted-test-secret',
            'events' => json_encode(['link.clicked']),
            'revision' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->deleteAccount($member);

        $reports = new \ArrayObject();
        $this->captureReportedThrowables($reports);
        Queue::fake();

        $this->withCookie(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED)
            ->get('/retained-hook-link')
            ->assertRedirect('https://example.com/retained-hook');

        $event = AnalyticsEvent::query()->sole();
        self::assertSame('link.clicked', $event->event_name);
        self::assertSame($workspace->id, $event->workspace_id);
        self::assertNull($event->user_id);

        $delivery = WebhookDelivery::query()->sole();
        self::assertSame($webhookId, $delivery->webhook_id);
        self::assertSame($event->uuid, $delivery->event_uuid);
        self::assertSame('link.clicked', $delivery->event_name);
        self::assertSame('pending', $delivery->state);
        self::assertSame(WebhookPayload::fromEvent($event), $delivery->body);
        self::assertSame($workspace->id, json_decode($delivery->body, true, 512, JSON_THROW_ON_ERROR)['workspace_id']);
        Queue::assertPushed(DeliverWebhook::class);
        self::assertCount(0, $reports, 'Consented delivery after creator deletion must not report errors.');
    }

    /**
     * @return array{0: Workspace, 1: User}
     */
    private function teamWorkspaceWithMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        return [$workspace, $member];
    }

    private function teamWorkspace(User $owner): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Attribution test team',
            'slug' => 'attribution-team-'.$owner->id,
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

    private function deleteAccount(User $user): void
    {
        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));
    }

    /**
     * Public delivery must stay silent even though the resource creator was
     * deleted: any reported throwable (previously a TypeError per visit) is a
     * regression in attribution, not an expected side effect.
     */
    private function captureReportedThrowables(\ArrayObject $reports): void
    {
        $this->app->instance(ExceptionHandler::class, new RecordingExceptionHandler(
            $this->app->make(ExceptionHandler::class),
            $reports,
        ));
    }
}

class RecordingExceptionHandler implements ExceptionHandler
{
    public function __construct(
        private readonly ExceptionHandler $original,
        private readonly \ArrayObject $reports,
    ) {}

    public function report(Throwable $e): void
    {
        $this->reports->append($e);
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function render($request, Throwable $e): Response
    {
        return $this->original->render($request, $e);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        $this->original->renderForConsole($output, $e);
    }
}
