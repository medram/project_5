<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\AnalyticsEvent;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\Webhooks\WebhookEndpointValidator;
use App\Services\Webhooks\WebhookPayload;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_store_validates_endpoint_and_event_names(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);
        $this->bindEndpointResolution(['8.8.8.8']);

        foreach (
            [
                'http://hooks.example.test/hook',
                'https://user:pass@hooks.example.test/hook',
                'https://127.0.0.1/hook',
                'https://10.0.0.1/hook',
                'https://169.254.1.1/hook',
                'https://192.168.1.1/hook',
            ] as $badUrl
        ) {
            $this->actingAs($owner)
                ->post('/account-settings/webhooks', $this->webhookPayload(['url' => $badUrl]))
                ->assertSessionHasErrors('url');
        }

        $this->actingAs($owner)
            ->post('/account-settings/webhooks', $this->webhookPayload(['events' => ['bogus.event']]))
            ->assertSessionHasErrors('events.0');

        $this->actingAs($owner)
            ->post('/account-settings/webhooks', $this->webhookPayload())
            ->assertRedirect('/account-settings/webhooks');

        $this->assertDatabaseCount('webhooks', 1);
    }

    public function test_webhook_creation_is_metered_by_the_account_plan(): void
    {
        $this->bindEndpointResolution(['8.8.8.8']);

        $free = User::factory()->create();

        $this->actingAs($free)
            ->post('/account-settings/webhooks', $this->webhookPayload())
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseCount('webhooks', 0);

        $pro = User::factory()->create();
        $this->makePro($pro);

        foreach (range(1, 5) as $index) {
            $this->actingAs($pro)
                ->post('/account-settings/webhooks', $this->webhookPayload(['name' => "Hook {$index}"]))
                ->assertRedirect('/account-settings/webhooks');
        }

        $this->actingAs($pro)
            ->post('/account-settings/webhooks', $this->webhookPayload(['name' => 'Hook 6']))
            ->assertSessionHasErrors('plan');

        $this->assertSame(5, $pro->currentWorkspace()->firstOrFail()->webhooks()->count());
    }

    public function test_webhook_rejects_hosts_resolving_to_non_public_addresses(): void
    {
        $owner = User::factory()->create();

        foreach ([
            ['203.0.113.10'], // reserved (TEST-NET)
            ['10.0.0.5'], // private
            ['127.0.0.1'], // loopback
        ] as $addresses) {
            $this->bindEndpointResolution($addresses);

            $this->actingAs($owner)
                ->post('/account-settings/webhooks', $this->webhookPayload())
                ->assertSessionHasErrors('url');
        }

        $this->assertDatabaseCount('webhooks', 0);
    }

    public function test_webhook_secrets_are_encrypted_and_exposed_only_once_on_create_and_rotation(): void
    {
        $owner = User::factory()->create();
        $this->makePro($owner);
        $this->bindEndpointResolution(['8.8.8.8']);

        $this->actingAs($owner)
            ->post('/account-settings/webhooks', $this->webhookPayload())
            ->assertRedirect('/account-settings/webhooks')
            ->assertSessionHas('webhookSecret');

        $secret = session('webhookSecret');
        $this->assertIsString($secret);
        $this->assertNotSame('', $secret);

        $stored = DB::table('webhooks')->value('secret');
        $this->assertNotSame($secret, $stored);

        $webhook = Webhook::query()->sole();

        $this->actingAs($owner)
            ->post("/account-settings/webhooks/{$webhook->id}/rotate")
            ->assertRedirect('/account-settings/webhooks')
            ->assertSessionHas('webhookSecret');

        $rotated = session('webhookSecret');
        $this->assertIsString($rotated);
        $this->assertNotSame($secret, $rotated);
        $this->assertNotSame($rotated, DB::table('webhooks')->value('secret'));

        // The rotate flash shows the new secret once; after that the page prop is secret-free.
        $this->actingAs($owner)
            ->get('/account-settings/webhooks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('webhooks/index')
                ->where('oneTimeSecret', $rotated));

        $this->actingAs($owner)
            ->get('/account-settings/webhooks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('webhooks/index')
                ->where('oneTimeSecret', null));
    }

    public function test_webhook_management_is_scoped_to_the_active_workspace_and_integration_managers(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $otherWorkspace = $other->personalWorkspace()->firstOrFail();
        $owner->workspaces()->attach($otherWorkspace, ['role' => 'member']);
        $otherWorkspace->subscription()->update([
            'plan_id' => Plan::query()->where('code', 'pro')->sole()->id,
        ]);
        $this->bindEndpointResolution(['8.8.8.8']);
        $webhook = $this->createWebhook($owner);

        $this->actingAs($owner)
            ->get('/account-settings/webhooks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageIntegrations', true)
                ->has('webhooks', 1));

        $this->app->make(WorkspaceContext::class)
            ->activate($owner, $otherWorkspace);
        $owner->refresh();
        $this->assertSame((int) $otherWorkspace->id, (int) $owner->current_workspace_id);
        $this->assertNotSame((int) $otherWorkspace->id, (int) $webhook->workspace_id);
        $this->actingAs($owner)
            ->get('/account-settings/webhooks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageIntegrations', false)
                ->has('webhooks', 0));

        $this->actingAs($owner)
            ->patch("/account-settings/webhooks/{$webhook->id}", $this->webhookPayload())
            ->assertNotFound();
        $this->actingAs($owner)
            ->post("/account-settings/webhooks/{$webhook->id}/rotate")
            ->assertNotFound();
        $this->actingAs($owner)
            ->delete("/account-settings/webhooks/{$webhook->id}")
            ->assertNotFound();

        $this->actingAs($owner)
            ->post('/account-settings/webhooks', $this->webhookPayload())
            ->assertForbidden();

        $this->app->make(WorkspaceContext::class)
            ->activate($owner, $otherWorkspace);
        $otherWorkspace->members()->updateExistingPivot($owner->id, ['role' => 'admin']);

        $this->actingAs($owner)
            ->post('/account-settings/webhooks', $this->webhookPayload())
            ->assertRedirect('/account-settings/webhooks');

        $this->assertSame(1, $otherWorkspace->webhooks()->count());
        $this->assertSame($owner->id, $otherWorkspace->webhooks()->sole()->user_id);
    }

    public function test_workspace_members_can_read_redacted_webhook_metadata_but_not_delivery_details(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $member = User::factory()->create();
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        $webhook = $this->createWebhook($owner, ['url' => 'https://hooks.example.test/private/token']);
        $delivery = $this->createDelivery(
            $webhook,
            $this->makeEvent($owner, Link::factory()->for($owner)->create()),
        );
        app(WorkspaceContext::class)->activate($member, $workspace);

        $this->actingAs($member)
            ->get('/account-settings/webhooks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageIntegrations', false)
                ->where('webhooks.0.url', null))
            ->assertDontSee('/private/token');
        $this->actingAs($member)
            ->getJson(route('webhooks.deliveries.index', $webhook))
            ->assertForbidden();
    }

    public function test_webhook_payload_carries_immutable_workspace_snapshot(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->firstOrFail();
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $event = $this->makeEvent($owner, $link);
        $payload = json_decode(WebhookPayload::fromEvent($event), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($workspace->id, $event->workspace_id);
        $this->assertSame($workspace->id, $payload['workspace_id']);
        $this->assertArrayNotHasKey('account_id', $payload);
    }

    public function test_workspace_webhook_receives_events_from_other_workspace_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->personalWorkspace()->firstOrFail();
        $workspace->members()->attach($member, ['role' => 'member']);
        $link = Link::factory()->create([
            'user_id' => $member->id,
            'workspace_id' => $workspace->id,
        ]);
        $webhook = $this->createWebhook($owner);
        Queue::fake();

        $this->recordEvent($this->requestWithConsent('granted'), $member, $link);

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame($webhook->id, $delivery->webhook_id);
        $payload = json_decode($delivery->body, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($workspace->id, $payload['workspace_id']);
        $this->assertArrayNotHasKey('account_id', $payload);
        Queue::assertPushed(DeliverWebhook::class);
    }

    public function test_persisting_consented_event_creates_delivery_and_dispatches_job(): void
    {
        $owner = User::factory()->create();
        $this->bindEndpointResolution(['8.8.8.8']);
        $link = Link::factory()->for($owner)->create();
        $this->createWebhook($owner, ['events' => ['link.clicked']]);

        Queue::fake();

        // No consent: no event, no delivery.
        $this->recordEvent($this->requestWithConsent(null), $owner, $link);
        $this->assertDatabaseCount('webhook_deliveries', 0);

        // Consented + subscribed: delivery + job.
        $this->recordEvent($this->requestWithConsent('granted'), $owner, $link);
        $this->assertDatabaseCount('webhook_deliveries', 1);
        Queue::assertPushed(DeliverWebhook::class);

        // Consented but unsubscribed event name: no delivery.
        $this->createWebhook($owner, ['events' => ['bio_page.view']]);
        $this->recordEvent($this->requestWithConsent('granted'), $owner, $link, 'overlay.view');
        $this->assertDatabaseCount('webhook_deliveries', 1);
    }

    public function test_consented_public_redirect_dispatches_the_link_clicked_webhook(): void
    {
        $owner = User::factory()->create();
        $link = Link::factory()->for($owner)->create([
            'slug' => 'webhook-direct',
            'destination_url' => 'https://example.com/webhook-direct',
        ]);
        $webhook = $this->createWebhook($owner, ['events' => ['link.clicked']]);
        Queue::fake();

        $this->withCookie(TrackingConsent::COOKIE_NAME, 'granted')
            ->get('/webhook-direct')
            ->assertRedirect('https://example.com/webhook-direct');

        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->id,
            'event_name' => 'link.clicked',
        ]);
        Queue::assertPushed(DeliverWebhook::class, 1);
    }

    public function test_webhook_payload_is_exact_and_delivery_is_signed(): void
    {
        $owner = User::factory()->create();
        $this->bindEndpointResolution(['8.8.8.8']);
        $link = Link::factory()->for($owner)->create(['slug' => 'launch-26']);
        $webhook = $this->createWebhook($owner, ['secret' => 'test-secret']);
        $event = $this->makeEvent($owner, $link, 'link.clicked');

        $expected = '{"schema_version":1,"event_id":"'.$event->uuid.'","event":"link.clicked","occurred_at":"'
            .$event->occurred_at->utc()->format('Y-m-d\TH:i:s\Z')
            .'","workspace_id":'.$link->workspace_id.',"data":{"link":{"id":'.$link->id.',"slug":"'.$link->slug.'"}}}';
        $this->assertSame($expected, WebhookPayload::fromEvent($event));

        $delivery = $this->createDelivery($webhook, $event);

        Http::fake();
        $this->runJob($delivery);

        Http::assertSent(function ($request) use ($expected, $webhook): bool {
            $signature = 'sha256='.hash_hmac('sha256', $expected, 'test-secret');

            return $request->url() === $webhook->url
                && $request->method() === 'POST'
                && $request->body() === $expected
                && $request->header('X-LinkPilot-Signature')[0] === $signature
                && $request->header('X-LinkPilot-Event')[0] === 'link.clicked'
                && $request->header('User-Agent')[0] === 'LinkPilot-Webhooks/1.0';
        });

        $this->assertSame('delivered', $delivery->fresh()->state);
    }

    public function test_delivery_history_exposes_the_bounded_response_excerpt_to_its_owner(): void
    {
        $owner = User::factory()->create();
        $webhook = $this->createWebhook($owner);
        $delivery = $this->createDelivery(
            $webhook,
            $this->makeEvent($owner, Link::factory()->for($owner)->create()),
        );
        $delivery->update([
            'attempt_count' => 1,
            'state' => 'failed',
            'response_code' => 500,
            'response_excerpt' => 'Temporary upstream failure.',
            'failure_detail' => 'Non-2xx response',
            'completed_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson(route('webhooks.deliveries.index', $webhook))
            ->assertOk()
            ->assertJsonPath('deliveries.0.responseExcerpt', 'Temporary upstream failure.');
    }

    public function test_successful_retry_clears_the_previous_failure_state(): void
    {
        $owner = User::factory()->create();
        $this->bindEndpointResolution(['8.8.8.8']);
        $webhook = $this->createWebhook($owner);
        $delivery = $this->createDelivery(
            $webhook,
            $this->makeEvent($owner, Link::factory()->for($owner)->create()),
        );
        $delivery->update([
            'attempt_count' => 1,
            'response_code' => 500,
            'response_excerpt' => 'Temporary upstream failure.',
            'failure_detail' => 'Non-2xx response',
        ]);
        Http::fake(['*' => Http::response('', 204)]);

        $this->runJob($delivery);

        $fresh = $delivery->fresh();
        $this->assertSame('delivered', $fresh->state);
        $this->assertSame(2, $fresh->attempt_count);
        $this->assertSame(204, $fresh->response_code);
        $this->assertNull($fresh->failure_detail);
        $this->assertNull($fresh->response_excerpt);
    }

    public function test_failed_response_excerpt_is_bounded_to_configured_bytes_and_valid_utf8(): void
    {
        $owner = User::factory()->create();
        $this->bindEndpointResolution(['8.8.8.8']);
        $webhook = $this->createWebhook($owner);
        $delivery = $this->createDelivery(
            $webhook,
            $this->makeEvent($owner, Link::factory()->for($owner)->create()),
        );
        $responseBody = str_repeat('😀', 150);

        config(['services.linkpilot.webhooks.excerpt_bytes' => 499]);
        Queue::fake();
        Http::fake(['*' => Http::response($responseBody, 500)]);

        $this->runJob($delivery);

        $excerpt = $delivery->fresh()->response_excerpt;
        $this->assertSame(496, strlen($excerpt));
        $this->assertLessThanOrEqual(499, strlen($excerpt));
        $this->assertSame(str_repeat('😀', 124), $excerpt);
        $this->assertTrue(mb_check_encoding($excerpt, 'UTF-8'));
    }

    public function test_delivery_retries_follow_configured_schedule_then_fail(): void
    {
        $owner = User::factory()->create();
        $this->bindEndpointResolution(['8.8.8.8']);
        $webhook = $this->createWebhook($owner);
        $delivery = $this->createDelivery($webhook, $this->makeEvent($owner, $link = Link::factory()->for($owner)->create()));

        config(['services.linkpilot.webhooks.retry_delays' => [60, 300]]);

        Queue::fake();
        Http::fake(['*' => Http::response('nope', 500)]);

        $this->runJob($delivery);
        $this->assertSame(1, $delivery->fresh()->attempt_count);
        $this->assertSame('pending', $delivery->fresh()->state);
        Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->delay === 60);

        $this->runJob($delivery);
        $this->assertSame(2, $delivery->fresh()->attempt_count);
        Queue::assertPushed(DeliverWebhook::class, fn ($job) => $job->delay === 300);

        $this->runJob($delivery);
        $this->assertSame(3, $delivery->fresh()->attempt_count);
        $this->assertSame('failed', $delivery->fresh()->state);
    }

    public function test_delivery_revalidates_endpoint_each_attempt_and_sends_no_request_when_invalid(): void
    {
        $owner = User::factory()->create();
        $webhook = $this->createWebhook($owner);
        $delivery = $this->createDelivery($webhook, $this->makeEvent($owner, Link::factory()->for($owner)->create()));

        // Endpoint now resolves to a private address.
        $this->bindEndpointResolution(['10.0.0.1']);

        Queue::fake();
        Http::fake();

        $this->runJob($delivery);

        Http::assertNothingSent();
        $this->assertSame(1, $delivery->fresh()->attempt_count);
        $this->assertSame('pending', $delivery->fresh()->state);
    }

    public function test_delivery_cancels_on_stale_revision_or_inactive_webhook_without_request(): void
    {
        $owner = User::factory()->create();
        $this->bindEndpointResolution(['8.8.8.8']);
        $webhook = $this->createWebhook($owner);
        $delivery = $this->createDelivery($webhook, $this->makeEvent($owner, Link::factory()->for($owner)->create()));

        $webhook->update(['revision' => $webhook->revision + 1]);

        Http::fake();
        $this->runJob($delivery);

        Http::assertNothingSent();
        $this->assertSame('cancelled', $delivery->fresh()->state);
    }

    public function test_mutating_webhook_cancels_pending_deliveries_but_preserves_history(): void
    {
        $owner = User::factory()->create();
        $this->bindEndpointResolution(['8.8.8.8']);
        $webhook = $this->createWebhook($owner);
        $event = $this->makeEvent($owner, Link::factory()->for($owner)->create());

        foreach (['update', 'rotate', 'disable', 'delete'] as $mutation) {
            $delivery = $this->createDelivery($webhook, $event);

            $this->actingAs($owner);

            match ($mutation) {
                'update' => $this->patch("/account-settings/webhooks/{$webhook->id}", $this->webhookPayload()),
                'rotate' => $this->post("/account-settings/webhooks/{$webhook->id}/rotate"),
                'disable' => $this->patch("/account-settings/webhooks/{$webhook->id}", $this->webhookPayload(['is_active' => false])),
                'delete' => $this->delete("/account-settings/webhooks/{$webhook->id}"),
            };

            $this->assertSame('cancelled', $delivery->fresh()->state);
            $this->assertDatabaseHas('webhook_deliveries', ['id' => $delivery->id]);
        }
    }

    private function webhookPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Launch hook',
            'url' => 'https://hooks.example.test/hook',
            'events' => ['link.clicked'],
            'is_active' => true,
        ], $overrides);
    }

    private function createWebhook(User $user, array $overrides = []): Webhook
    {
        $secret = $overrides['secret'] ?? ('secret-'.Str::random(8));
        unset($overrides['secret']);

        $workspace = $user->currentWorkspace()->firstOrFail();
        $webhook = $workspace->webhooks()->make(array_merge([
            'name' => 'Hook',
            'url' => 'https://hooks.example.test/hook',
            'events' => ['link.clicked'],
            'revision' => 1,
            'is_active' => true,
        ], $overrides));
        $webhook->forceFill([
            'user_id' => $user->id,
            'secret' => $secret,
        ])->save();

        return $webhook;
    }

    private function createDelivery(Webhook $webhook, AnalyticsEvent $event): WebhookDelivery
    {
        return WebhookDelivery::query()->create([
            'webhook_id' => $webhook->id,
            'revision' => $webhook->revision,
            'event_uuid' => $event->uuid,
            'event_name' => $event->event_name,
            'body' => WebhookPayload::fromEvent($event),
            'attempt_count' => 0,
            'state' => 'pending',
        ]);
    }

    private function makeEvent(User $user, Link $link, string $eventName = 'link.clicked'): AnalyticsEvent
    {
        return AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $link->workspace_id,
            'user_id' => $user->id,
            'link_id' => $link->id,
            'event_name' => $eventName,
            'occurred_at' => now('UTC')->startOfSecond(),
            'visitor_hash' => Str::padRight('', 64, 'a'),
            'visitor_key_version' => 'test-v1',
        ]);
    }

    private function recordEvent(Request $request, User $user, Link $link, string $eventName = 'link.clicked'): void
    {
        app(AnalyticsTracker::class)->record($request, $eventName, $user, [
            'link' => $link,
        ]);
    }

    private function requestWithConsent(?string $consent): Request
    {
        $request = Request::create('/', 'GET');

        if ($consent !== null) {
            $request->cookies->set(TrackingConsent::COOKIE_NAME, $consent);
        }

        return $request;
    }

    private function runJob(WebhookDelivery $delivery): void
    {
        $this->app->call([new DeliverWebhook($delivery->id), 'handle']);
    }

    private function bindEndpointResolution(array $addresses): void
    {
        $this->app->instance(WebhookEndpointValidator::class, new class($addresses) extends WebhookEndpointValidator
        {
            /**
             * @param  list<string>  $addresses
             */
            public function __construct(private readonly array $addresses) {}

            /**
             * @return list<string>
             */
            protected function resolveAddresses(string $host): array
            {
                return $this->addresses;
            }
        });
    }

    private function makePro(User $user): void
    {
        $user->currentWorkspace()->firstOrFail()->subscription()->update([
            'plan_id' => Plan::query()->where('code', 'pro')->sole()->id,
        ]);
    }
}
