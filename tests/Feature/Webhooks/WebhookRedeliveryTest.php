<?php

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\AnalyticsEvent;
use App\Models\Link;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Analytics\TrackingConsent;
use App\Services\Webhooks\WebhookEndpointValidator;
use App\Services\Webhooks\WebhookPayload;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebhookRedeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_and_cancelled_sources_create_immutable_current_revision_redeliveries(): void
    {
        $owner = User::factory()->create();
        $webhook = $this->createWebhook($owner, [
            'revision' => 2,
            'url' => 'https://hooks.example.test/v2',
        ]);
        $webhook->forceFill(['secret' => 'current-secret'])->save();
        $event = $this->makeEvent($owner);
        $failed = $this->createDelivery($webhook, $event, 'failed', 1);
        $cancelled = $this->createDelivery($webhook, $event, 'cancelled', 1);
        $failedBefore = $failed->fresh()->getAttributes();
        $cancelledBefore = $cancelled->fresh()->getAttributes();
        Queue::fake();
        Http::fake(['*' => Http::response('', 204)]);
        $this->bindEndpointResolution(['8.8.8.8']);

        $this->actingAs($owner)
            ->post($this->redeliverPath($webhook, $failed))
            ->assertRedirect('/account-settings/webhooks');

        $failedRedelivery = WebhookDelivery::query()
            ->where('source_delivery_id', $failed->id)
            ->sole();
        $this->assertSame($webhook->id, $failedRedelivery->webhook_id);
        $this->assertSame(2, $failedRedelivery->revision);
        $this->assertSame($event->uuid, $failedRedelivery->event_uuid);
        $this->assertSame($failed->body, $failedRedelivery->body);
        $this->assertSame('pending', $failedRedelivery->state);
        $this->assertSame(0, $failedRedelivery->attempt_count);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $failedRedelivery->id);

        $this->app->call([new DeliverWebhook($failedRedelivery->id), 'handle']);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://hooks.example.test/v2'
            && $request->header('X-LinkPilot-Signature')[0] === 'sha256='.hash_hmac(
                'sha256',
                $failedRedelivery->body,
                'current-secret',
            ));
        $this->assertSame('delivered', $failedRedelivery->fresh()->state);

        $this->actingAs($owner)
            ->post($this->redeliverPath($webhook, $cancelled))
            ->assertRedirect('/account-settings/webhooks');

        $cancelledRedelivery = WebhookDelivery::query()
            ->where('source_delivery_id', $cancelled->id)
            ->sole();
        $this->assertSame(2, $cancelledRedelivery->revision);
        $this->assertSame($cancelled->body, $cancelledRedelivery->body);
        $this->assertSame('pending', $cancelledRedelivery->state);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $cancelledRedelivery->id);
        $this->assertEqualsCanonicalizing($failedBefore, $failed->fresh()->getAttributes());
        $this->assertEqualsCanonicalizing($cancelledBefore, $cancelled->fresh()->getAttributes());
    }

    public function test_pending_and_delivered_sources_cannot_be_redelivered(): void
    {
        $owner = User::factory()->create();
        $webhook = $this->createWebhook($owner);
        $event = $this->makeEvent($owner);
        $pending = $this->createDelivery($webhook, $event, 'pending', $webhook->revision);
        $delivered = $this->createDelivery($webhook, $event, 'delivered', $webhook->revision);
        Queue::fake();

        $this->actingAs($owner)
            ->post($this->redeliverPath($webhook, $pending))
            ->assertConflict();
        $this->actingAs($owner)
            ->post($this->redeliverPath($webhook, $delivered))
            ->assertConflict();

        $this->assertDatabaseCount('webhook_deliveries', 2);
        Queue::assertNothingPushed();
    }

    public function test_only_workspace_integration_managers_can_redeliver_their_workspace_deliveries(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $webhook = $this->createWebhook($owner);
        $source = $this->createDelivery($webhook, $this->makeEvent($owner), 'failed', 1);
        $member = User::factory()->create();
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        Queue::fake();

        $this->actingAs($member)
            ->post($this->redeliverPath($webhook, $source))
            ->assertForbidden();
        Auth::forgetGuards();

        $otherOwner = User::factory()->create();
        $otherWorkspace = $otherOwner->personalWorkspace()->firstOrFail();
        $owner->workspaces()->attach($otherWorkspace, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($owner, $otherWorkspace);
        $this->actingAs($owner)
            ->post($this->redeliverPath($webhook, $source))
            ->assertNotFound();
        Auth::forgetGuards();

        $admin = User::factory()->create();
        $admin->workspaces()->attach($workspace, ['role' => 'admin']);
        app(WorkspaceContext::class)->activate($admin, $workspace);
        $this->actingAs($admin)
            ->post($this->redeliverPath($webhook, $source))
            ->assertRedirect('/account-settings/webhooks');
        $this->assertDatabaseHas('webhook_deliveries', [
            'source_delivery_id' => $source->id,
            'webhook_id' => $webhook->id,
        ]);
    }

    public function test_endpoint_health_summarizes_only_the_current_webhook_revision(): void
    {
        $owner = User::factory()->create();
        $webhook = $this->createWebhook($owner, ['revision' => 3]);
        $event = $this->makeEvent($owner);
        $this->createDelivery($webhook, $event, 'delivered', 3);
        $this->createDelivery($webhook, $event, 'failed', 3);
        $latest = $this->createDelivery($webhook, $event, 'pending', 3);
        $this->createDelivery($webhook, $event, 'failed', 2);

        $this->actingAs($owner)
            ->get('/account-settings/webhooks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('webhooks.0.health.latestState', $latest->state)
                ->where('webhooks.0.health.counts.delivered', 1)
                ->where('webhooks.0.health.counts.failed', 1)
                ->where('webhooks.0.health.counts.pending', 1)
                ->where('webhooks.0.health.counts.cancelled', 0));
    }

    private function createWebhook(User $user, array $overrides = []): Webhook
    {
        $webhook = $user->currentWorkspace()->firstOrFail()->webhooks()->make(array_merge([
            'name' => 'Hook',
            'url' => 'https://hooks.example.test/hook',
            'events' => ['link.clicked'],
            'revision' => 1,
            'is_active' => true,
        ], $overrides));
        $webhook->forceFill([
            'user_id' => $user->id,
            'secret' => 'initial-secret',
        ])->save();

        return $webhook;
    }

    private function makeEvent(User $user): AnalyticsEvent
    {
        $link = Link::factory()->for($user)->create();

        return AnalyticsEvent::query()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $link->workspace_id,
            'user_id' => $user->id,
            'link_id' => $link->id,
            'event_name' => 'link.clicked',
            'occurred_at' => now('UTC')->startOfSecond(),
            'visitor_hash' => Str::padRight('', 64, 'a'),
            'visitor_key_version' => 'test-v1',
        ]);
    }

    private function createDelivery(
        Webhook $webhook,
        AnalyticsEvent $event,
        string $state,
        int $revision,
    ): WebhookDelivery {
        return $webhook->deliveries()->create([
            'revision' => $revision,
            'event_uuid' => $event->uuid,
            'event_name' => $event->event_name,
            'body' => WebhookPayload::fromEvent($event),
            'attempt_count' => in_array($state, ['failed', 'delivered'], true) ? 1 : 0,
            'state' => $state,
            'response_code' => $state === 'delivered' ? 204 : ($state === 'failed' ? 500 : null),
            'response_excerpt' => $state === 'failed' ? 'Temporary failure' : null,
            'failure_detail' => $state === 'failed' ? 'Non-2xx response' : null,
            'completed_at' => in_array($state, ['failed', 'delivered', 'cancelled'], true) ? now() : null,
        ]);
    }

    private function redeliverPath(Webhook $webhook, WebhookDelivery $delivery): string
    {
        return "/account-settings/webhooks/{$webhook->id}/deliveries/{$delivery->id}/redeliver";
    }

    /** @param list<string> $addresses */
    private function bindEndpointResolution(array $addresses): void
    {
        $this->app->instance(WebhookEndpointValidator::class, new class($addresses) extends WebhookEndpointValidator
        {
            public function __construct(private readonly array $addresses) {}

            protected function resolveAddresses(string $host): array
            {
                return $this->addresses;
            }
        });
    }
}
