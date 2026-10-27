<?php

namespace Tests\Feature\Api;

use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Webhooks\WebhookEndpointValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class VersionedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_bound_link_api_paginates_and_hides_foreign_resources_even_after_workspace_switch(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $otherWorkspace = $this->teamWorkspace($owner, 'api-other-workspace');
        $links = Link::factory()->count(3)->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
        ])->sortBy('id')->values();
        $foreignOwner = User::factory()->create();
        $foreignLink = Link::factory()->create([
            'user_id' => $foreignOwner->id,
            'workspace_id' => $foreignOwner->current_workspace_id,
        ]);
        $token = $this->token($owner, $workspace, ['links:read']);

        $owner->update(['current_workspace_id' => $otherWorkspace->id]);

        $firstPage = $this->withToken($token)->getJson('/api/v1/links?per_page=2');
        $firstPage->assertOk()
            ->assertJsonPath('data.0.id', $links[0]->id)
            ->assertJsonPath('data.1.id', $links[1]->id)
            ->assertJsonPath('meta.per_page', 2);

        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertIsString($cursor);
        $this->withToken($token)
            ->getJson('/api/v1/links?per_page=2&cursor='.rawurlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.0.id', $links[2]->id)
            ->assertJsonPath('meta.next_cursor', null);

        $writerToken = $this->token($owner, $workspace, ['links:read', 'links:write']);
        Auth::forgetGuards();
        $this->withToken($writerToken)->getJson('/api/v1/links/'.$links[0]->id)
            ->assertOk()
            ->assertJsonPath('data.id', $links[0]->id);
        $this->withToken($writerToken)->patchJson('/api/v1/links/'.$links[0]->id, [
            'title' => 'API updated link',
        ])->assertOk()->assertJsonPath('data.title', 'API updated link');
        $this->withToken($writerToken)->deleteJson('/api/v1/links/'.$links[0]->id)->assertNoContent();
        $this->assertDatabaseMissing('links', ['id' => $links[0]->id]);

        $this->withToken($writerToken)->getJson('/api/v1/links/'.$foreignLink->id)->assertNotFound();
    }

    public function test_api_requires_workspace_tokens_and_enforces_abilities_expiry_revocation_and_membership(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $readOnlyToken = $this->token($owner, $workspace, ['links:read']);

        $this->withToken($readOnlyToken)->postJson('/api/v1/links', [
            'destination_url' => 'https://destination.example/landing',
        ])->assertForbidden();
        Auth::forgetGuards();

        $expiredToken = $owner->createWorkspaceApiToken(
            $workspace,
            'expired api test',
            ['links:read'],
            now()->subMinute(),
        )->plainTextToken;
        $this->withToken($expiredToken)->getJson('/api/v1/links')->assertUnauthorized();
        Auth::forgetGuards();

        $this->actingAs($owner)->getJson('/api/v1/links')->assertUnauthorized();
        Auth::forgetGuards();

        $revokedToken = $this->token($owner, $workspace, ['links:read']);
        DB::table('personal_access_tokens')
            ->where('id', explode('|', $revokedToken, 2)[0])
            ->delete();
        Auth::forgetGuards();
        $this->withToken($revokedToken)->getJson('/api/v1/links')->assertUnauthorized();
        Auth::forgetGuards();

        $detachedToken = $this->token($owner, $workspace, ['links:read']);
        $owner->workspaces()->detach($workspace->getKey());
        Auth::forgetGuards();
        $this->withToken($detachedToken)->getJson('/api/v1/links')->assertForbidden();
    }

    public function test_create_idempotency_replays_one_resource_and_audit_rows_never_store_request_data(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $token = $this->token($owner, $workspace, ['links:write', 'links:read']);
        $payload = [
            'destination_url' => 'https://destination.example/private-campaign',
            'title' => 'Campaign landing',
        ];

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'campaign-create-001')
            ->postJson('/api/v1/links', $payload);
        $first->assertCreated()->assertJsonPath('data.title', 'Campaign landing');

        $replay = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'campaign-create-001')
            ->postJson('/api/v1/links', $payload);
        $replay->assertCreated()->assertExactJson($first->json());
        $this->assertSame(1, $workspace->links()->count());

        $conflict = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'campaign-create-001')
            ->postJson('/api/v1/links', [
                ...$payload,
                'destination_url' => 'https://destination.example/changed',
            ]);
        $conflict->assertConflict()->assertJsonPath('error.code', 'idempotency_conflict');

        $this->assertSame(3, DB::table('api_audit_logs')->where('workspace_id', $workspace->id)->count());
        $audit = DB::table('api_audit_logs')->first();
        $this->assertSame($owner->id, (int) $audit->actor_user_id);
        $this->assertSame('POST', $audit->method);
        $this->assertSame(201, (int) $audit->status_code);
        $this->assertStringNotContainsString('destination.example', json_encode($audit, JSON_THROW_ON_ERROR));
        $this->assertFalse(Schema::hasColumn('api_audit_logs', 'request_body'));

        $storedResponse = DB::table('api_idempotency_keys')->value('response_body');
        $this->assertIsString($storedResponse);
        $this->assertStringNotContainsString('destination.example', $storedResponse);
    }

    public function test_bio_and_link_page_api_supports_workspace_scoped_create_update_and_delete(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $token = $this->token($owner, $workspace, [
            'bio-pages:read', 'bio-pages:write', 'link-pages:read', 'link-pages:write',
        ]);
        $foreignOwner = User::factory()->create();
        $this->withToken($token)->postJson('/api/v1/bio-pages', [
            'workspace_id' => $foreignOwner->current_workspace_id,
            'handle' => 'foreign-target',
            'display_name' => 'Foreign target',
            'is_published' => false,
        ])->assertUnprocessable();

        $bioPage = $this->withToken($token)->postJson('/api/v1/bio-pages', [
            'handle' => 'api-profile',
            'display_name' => 'API Profile',
            'bio' => 'Created through the API',
            'is_published' => false,
        ])->assertCreated()->assertJsonPath('data.handle', 'api-profile');
        $bioPageId = $bioPage->json('data.id');
        $this->withToken($token)->patchJson('/api/v1/bio-pages/'.$bioPageId, [
            'display_name' => 'Updated Profile',
        ])->assertOk()->assertJsonPath('data.display_name', 'Updated Profile');
        $this->withToken($token)->deleteJson('/api/v1/bio-pages/'.$bioPageId)->assertNoContent();
        $this->assertDatabaseMissing('bio_pages', ['id' => $bioPageId]);

        $linkPage = $this->withToken($token)->postJson('/api/v1/link-pages', [
            'name' => 'API Campaign',
            'slug' => 'api-campaign',
            'title' => 'API Campaign',
            'description' => 'Created through the API',
            'template' => 'campaign',
            'is_published' => false,
        ])->assertCreated()->assertJsonPath('data.slug', 'api-campaign');
        $linkPageId = $linkPage->json('data.id');
        $this->assertDatabaseHas('link_page_variants', [
            'link_page_id' => $linkPageId,
            'is_control' => true,
        ]);
        $this->withToken($token)->patchJson('/api/v1/link-pages/'.$linkPageId, [
            'title' => 'Updated Campaign',
        ])->assertOk()->assertJsonPath('data.title', 'Updated Campaign');
        $this->withToken($token)->deleteJson('/api/v1/link-pages/'.$linkPageId)->assertNoContent();
        $this->assertDatabaseMissing('link_pages', ['id' => $linkPageId]);
    }

    public function test_webhook_api_returns_a_secret_once_and_never_in_resource_reads(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $this->makePro($owner);
        $this->bindEndpointResolution(['8.8.8.8']);
        $token = $this->token($owner, $workspace, ['webhooks:read', 'webhooks:write']);

        $created = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'webhook-create-001')
            ->postJson('/api/v1/webhooks', [
                'name' => 'API events',
                'url' => 'https://hooks.example.test/events',
                'events' => ['link.clicked'],
                'is_active' => true,
            ]);
        $created->assertCreated()->assertJsonPath('data.name', 'API events');
        $secret = $created->json('secret');
        $this->assertIsString($secret);
        $this->assertNotSame('', $secret);

        $webhookId = $created->json('data.id');
        $this->withToken($token)->getJson('/api/v1/webhooks/'.$webhookId)
            ->assertOk()
            ->assertJsonMissingPath('data.secret');
        $this->withToken($token)->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonMissingPath('data.0.secret');

        $this->withToken($token)->patchJson('/api/v1/webhooks/'.$webhookId, [
            'name' => 'Updated API events',
        ])->assertOk()->assertJsonMissingPath('data.secret');
        $this->withToken($token)->deleteJson('/api/v1/webhooks/'.$webhookId)->assertNoContent();

        $storedSecret = DB::table('webhooks')->where('id', $webhookId)->value('secret');
        $this->assertNotSame($secret, $storedSecret);
    }

    public function test_analytics_export_and_declared_event_ingestion_are_bound_to_the_token_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $foreignOwner = User::factory()->create();
        $link = Link::factory()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspace->id,
        ]);
        $foreignLink = Link::factory()->create([
            'user_id' => $foreignOwner->id,
            'workspace_id' => $foreignOwner->current_workspace_id,
        ]);
        $workspace->analyticsEventDefinitions()->create([
            'event_name' => 'signup.completed',
            'is_active' => true,
        ]);
        $workspace->analyticsEvents()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $owner->id,
            'link_id' => $link->id,
            'event_name' => 'link.clicked',
            'occurred_at' => now(),
            'visitor_hash' => str_repeat('a', 64),
            'visitor_key_version' => 'local',
        ]);
        $foreignEvent = $foreignOwner->currentWorkspace()->firstOrFail()->analyticsEvents()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $foreignOwner->id,
            'link_id' => $foreignLink->id,
            'event_name' => 'link.clicked',
            'occurred_at' => now(),
            'visitor_hash' => str_repeat('b', 64),
            'visitor_key_version' => 'local',
        ]);
        $token = $this->token($owner, $workspace, ['analytics:read', 'events:write']);

        $csv = $this->withToken($token)->get('/api/v1/analytics/exports?days=30');
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('link.clicked', $csv->streamedContent());
        $this->assertStringNotContainsString($foreignEvent->uuid, $csv->streamedContent());
        $this->assertStringNotContainsString('visitor_hash', $csv->streamedContent());

        $event = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'event-ingest-001')
            ->postJson('/api/v1/events', [
                'event_name' => 'signup.completed',
                'resource_type' => 'link',
                'resource_id' => $link->id,
            ]);
        $event->assertCreated()->assertJsonPath('data.event_name', 'signup.completed');
        $eventId = $event->json('data.id');
        $this->assertDatabaseHas('analytics_events', [
            'id' => $eventId,
            'workspace_id' => $workspace->id,
            'link_id' => $link->id,
            'visitor_hash' => null,
            'country_code' => null,
        ]);

        $this->withToken($token)->withHeader('Idempotency-Key', 'event-foreign-resource-001')->postJson('/api/v1/events', [
            'event_name' => 'signup.completed',
            'resource_type' => 'link',
            'resource_id' => $foreignLink->id,
        ])->assertNotFound();
        $this->withToken($token)->withHeader('Idempotency-Key', 'event-undeclared-001')->postJson('/api/v1/events', [
            'event_name' => 'undeclared.event',
            'resource_type' => 'link',
            'resource_id' => $link->id,
        ])->assertUnprocessable();
        $this->withToken($token)->withHeader('Idempotency-Key', 'event-properties-001')->postJson('/api/v1/events', [
            'event_name' => 'signup.completed',
            'resource_type' => 'link',
            'resource_id' => $link->id,
            'properties' => ['private' => true],
        ])->assertUnprocessable();
    }

    public function test_validation_rate_limit_errors_and_generated_openapi_contract_are_stable(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $token = $this->token($owner, $workspace, ['links:read', 'links:write']);

        $invalid = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'invalid-link-001')
            ->postJson('/api/v1/links', ['destination_url' => 'javascript:alert(1)']);
        $invalid->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['destination_url']]]);

        config(['linkpilot.api.rate_limit_per_minute' => 1]);
        $rateToken = $this->token($owner, $workspace, ['links:read']);
        Auth::forgetGuards();
        $this->withToken($rateToken)->getJson('/api/v1/links')->assertOk();
        $this->withToken($rateToken)->getJson('/api/v1/links')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'rate_limited');

        $openApi = $this->getJson('/docs/api.json');
        $openApi->assertOk()->assertJsonStructure(['openapi', 'paths']);
        $this->assertArrayHasKey('/v1/links', $openApi->json('paths'));
        $this->assertArrayHasKey('/v1/events', $openApi->json('paths'));
        $this->assertSame('bearer', $openApi->json('components.securitySchemes.http.scheme'));
        $this->assertNotEmpty($openApi->json('security'));
        $daysParameter = collect($openApi->json('paths./v1/analytics/exports.get.parameters'))
            ->firstWhere('name', 'days');
        $this->assertNotNull($daysParameter);
        $this->assertSame('#/components/schemas/AnalyticsWindow', $daysParameter['schema']['$ref']);
        $this->assertSame('integer', $openApi->json('components.schemas.AnalyticsWindow.type'));
        $this->assertSame([7, 30, 90], $openApi->json('components.schemas.AnalyticsWindow.enum'));
    }

    private function token(User $user, Workspace $workspace, array $abilities): string
    {
        return $user->createWorkspaceApiToken($workspace, 'Versioned API test', $abilities)->plainTextToken;
    }

    private function teamWorkspace(User $owner, string $slug): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'API Team',
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

    private function makePro(User $user): void
    {
        $user->currentWorkspace()->firstOrFail()->subscription()->update([
            'plan_id' => Plan::query()->where('code', 'pro')->sole()->id,
        ]);
    }

    /** @param list<string> $addresses */
    private function bindEndpointResolution(array $addresses): void
    {
        $this->app->instance(WebhookEndpointValidator::class, new class($addresses) extends WebhookEndpointValidator
        {
            /** @param list<string> $addresses */
            public function __construct(private readonly array $addresses) {}

            /** @return list<string> */
            protected function resolveAddresses(string $host): array
            {
                return $this->addresses;
            }
        });
    }
}
