<?php

namespace Tests\Feature\Domains;

use App\Models\CustomDomain;
use App\Models\Link;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Domains\DnsTxtLookup;
use App\Services\Domains\DomainVerificationService;
use App\Services\Domains\HttpsDomainChallengeProbe;
use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_hostname_rejects_unsafe_and_default_hosts(): void
    {
        config()->set('linkpilot.default_hosts', ['app.linkpilot.test']);
        $user = User::factory()->create();

        foreach ([
            '127.0.0.1',
            'localhost',
            'http://go.example.test',
            'app.linkpilot.test',
            'not a hostname',
        ] as $hostname) {
            $this->actingAs($user)
                ->post('/dashboard/custom-domains', ['hostname' => $hostname])
                ->assertSessionHasErrors('hostname');
        }
    }

    public function test_owner_creates_normalized_domain_with_exact_setup_instructions(): void
    {
        $user = $this->userWithPlan('pro');

        $this->actingAs($user)
            ->post('/dashboard/custom-domains', ['hostname' => 'Go.Example.Test.'])
            ->assertRedirect('/dashboard/custom-domains');

        $domain = CustomDomain::query()->sole();

        $this->assertSame($user->id, $domain->user_id);
        $this->assertSame('go.example.test', $domain->hostname);
        $this->assertSame('pending_dns', $domain->status);
        $this->assertNotSame('', $domain->verification_token);

        $this->withoutVite();

        $this->actingAs($user)
            ->get('/dashboard/custom-domains')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('custom-domains/index')
                ->where('domains.0.hostname', 'go.example.test')
                ->where('domains.0.txtHost', '_linkpilot-verify.go.example.test')
                ->where('domains.0.txtValue', "linkpilot={$domain->verification_token}")
                ->where('domains.0.challengeUrl', "https://go.example.test/.well-known/linkpilot-domain/{$domain->verification_token}")
                ->where('canManageIntegrations', true));
    }

    public function test_workspace_member_sees_only_active_domain_metadata_without_verification_tokens(): void
    {
        $owner = $this->userWithPlan('pro');
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $member = User::factory()->create();
        $member->workspaces()->attach($workspace, ['role' => 'member']);
        $pending = $this->domainFor($owner, [
            'hostname' => 'pending.example.test',
            'verification_token' => 'pending-verification-secret',
        ]);
        $active = $this->domainFor($owner, [
            'hostname' => 'active.example.test',
            'verification_token' => 'active-verification-secret',
            'status' => 'active',
        ]);
        app(WorkspaceContext::class)->activate($member, $workspace);

        $this->actingAs($member)
            ->get('/dashboard/custom-domains')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManageIntegrations', false)
                ->has('domains', 1)
                ->where('domains.0.hostname', $active->hostname)
                ->missing('domains.0.verificationToken')
                ->missing('domains.0.challengeUrl')
                ->missing('domains.0.txtValue'))
            ->assertDontSee($pending->verification_token)
            ->assertDontSee($active->verification_token);
    }

    public function test_domain_management_is_workspace_scoped_and_limited_to_integration_managers(): void
    {
        $owner = $this->userWithPlan('pro');
        $other = User::factory()->create();
        $otherWorkspace = $other->personalWorkspace()->firstOrFail();
        $owner->workspaces()->attach($otherWorkspace, ['role' => 'member']);
        app(WorkspaceUsage::class)->assignPlan($otherWorkspace, Plan::query()->where('code', 'pro')->sole());
        $domain = $this->domainFor($owner);

        $this->actingAs($owner)
            ->get('/dashboard/custom-domains')
            ->assertOk()
            ->assertSee($domain->hostname);

        app(WorkspaceContext::class)->activate($owner, $otherWorkspace);

        $this->actingAs($owner)
            ->get('/dashboard/custom-domains')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('domains', [])
                ->where('canManageIntegrations', false));
        $this->actingAs($owner)
            ->post("/dashboard/custom-domains/{$domain->id}/verify")
            ->assertNotFound();
        $this->actingAs($owner)
            ->post("/dashboard/custom-domains/{$domain->id}/reissue")
            ->assertNotFound();

        $this->actingAs($owner)
            ->delete("/dashboard/custom-domains/{$domain->id}", ['confirmation' => $domain->hostname])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post('/dashboard/custom-domains', ['hostname' => 'member.example.test'])
            ->assertForbidden();

        $otherWorkspace->members()->updateExistingPivot($owner->id, ['role' => 'admin']);
        $this->actingAs($owner)
            ->post('/dashboard/custom-domains', ['hostname' => 'admin.example.test'])
            ->assertRedirect('/dashboard/custom-domains');

        $created = $otherWorkspace->customDomains()->sole();
        $this->assertSame($owner->id, $created->user_id);
    }

    public function test_verification_advances_dns_tls_active_then_fails_a_later_recheck(): void
    {
        [$dns, $https] = $this->fakeVerificationBoundaries();
        $user = User::factory()->create();
        $domain = $this->domainFor($user);

        $dns->records = [];

        $this->actingAs($user)
            ->post("/dashboard/custom-domains/{$domain->id}/verify")
            ->assertRedirect('/dashboard/custom-domains');

        $this->assertSame('pending_dns', $domain->refresh()->status);

        $dns->records = ["linkpilot={$domain->verification_token}"];
        $https->matches = false;

        $this->actingAs($user)
            ->post("/dashboard/custom-domains/{$domain->id}/verify")
            ->assertRedirect('/dashboard/custom-domains');

        $this->assertSame('pending_tls', $domain->refresh()->status);
        $this->assertSame($domain->hostname, $https->hostname);
        $this->assertSame($domain->verification_token, $https->token);

        $https->matches = true;

        $this->actingAs($user)
            ->post("/dashboard/custom-domains/{$domain->id}/verify")
            ->assertRedirect('/dashboard/custom-domains');

        $domain->refresh();

        $this->assertSame('active', $domain->status);
        $this->assertNotNull($domain->verified_at);
        $this->assertNotNull($domain->last_checked_at);

        $https->matches = false;

        $this->actingAs($user)
            ->post("/dashboard/custom-domains/{$domain->id}/verify")
            ->assertRedirect('/dashboard/custom-domains');

        $domain->refresh();

        $this->assertSame('failed', $domain->status);
        $this->assertNotNull($domain->failure_reason);
        $this->assertLessThanOrEqual(500, strlen($domain->failure_reason));
    }

    public function test_in_flight_verification_never_overwrites_a_reissued_token_state(): void
    {
        [$dns, $https] = $this->fakeVerificationBoundaries();
        $service = app(DomainVerificationService::class);
        $user = User::factory()->create();
        $scenarios = [
            'success' => ['dnsMatches' => true, 'httpsMatches' => true, 'throws' => false],
            'dns mismatch' => ['dnsMatches' => false, 'httpsMatches' => false, 'throws' => false],
            'tls mismatch' => ['dnsMatches' => true, 'httpsMatches' => false, 'throws' => false],
            'probe exception' => ['dnsMatches' => false, 'httpsMatches' => false, 'throws' => true],
        ];

        foreach ($scenarios as $name => $scenario) {
            $domain = $this->domainFor($user, [
                'hostname' => str_replace(' ', '-', $name).'.example.test',
                'status' => 'active',
                'verified_at' => now()->subMinute(),
                'last_checked_at' => now()->subMinute(),
            ]);
            $oldToken = $domain->verification_token;
            $dns->records = $scenario['dnsMatches'] ? ["linkpilot={$oldToken}"] : [];
            $dns->exception = $scenario['throws'] ? new RuntimeException('Probe unavailable.') : null;
            $dns->beforeLookup = function () use ($domain, $service): void {
                $service->reissueToken($domain->fresh());
            };
            $https->matches = $scenario['httpsMatches'];

            $service->verify($domain);

            $fresh = $domain->fresh();
            $this->assertNotSame($oldToken, $fresh->verification_token, $name);
            $this->assertSame('pending_dns', $fresh->status, $name);
            $this->assertNull($fresh->verified_at, $name);
            $this->assertNull($fresh->last_checked_at, $name);
            $this->assertNull($fresh->failure_reason, $name);
        }
    }

    public function test_https_probe_uses_prevalidated_public_addresses(): void
    {
        Http::fake([
            'https://verification.example.test/*' => Http::response('probe-token'),
        ]);

        $probe = new class extends HttpsDomainChallengeProbe
        {
            /**
             * @return list<string>
             */
            protected function resolvePublicAddresses(string $hostname): array
            {
                return ['8.8.8.8'];
            }
        };

        $this->assertTrue(
            $probe->respondsWithToken('verification.example.test', 'probe-token'),
        );

        Http::assertSent(
            fn (Request $request): bool => $request->url()
                === 'https://verification.example.test/.well-known/linkpilot-domain/probe-token',
        );
    }

    public function test_only_owner_can_verify_or_assign_active_domain_and_public_host_delivery_never_falls_back(): void
    {
        $owner = $this->userWithPlan('pro');
        $other = $this->userWithPlan('pro');
        $domain = $this->domainFor($owner, ['status' => 'active']);
        $link = $this->linkFor($owner, 'host-aware-link');
        $otherLink = $this->linkFor($other, 'other-host-aware-link');

        $this->actingAs($other)
            ->post("/dashboard/custom-domains/{$domain->id}/verify")
            ->assertNotFound();

        $this->actingAs($other)
            ->patch("/dashboard/links/{$otherLink->id}", $this->linkPayload($otherLink, [
                'custom_domain_id' => $domain->id,
            ]))
            ->assertSessionHasErrors('custom_domain_id');

        $this->actingAs($owner)
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link, [
                'custom_domain_id' => $domain->id,
            ]))
            ->assertRedirect('/dashboard/links');

        $this->assertSame($domain->id, $link->refresh()->custom_domain_id);

        $this->getFromHost('localhost', "/{$link->slug}")
            ->assertNotFound();

        $this->getFromHost($domain->hostname, "/{$link->slug}")
            ->assertRedirect($link->destination_url);

        $this->getFromHost('unknown.example.test', "/{$link->slug}")
            ->assertNotFound();

        $this->getFromHost("{$domain->hostname}:8443", "/{$link->slug}")
            ->assertNotFound();

        $domain->update(['status' => 'failed']);

        $this->getFromHost($domain->hostname, "/{$link->slug}")
            ->assertNotFound();

        $this->getFromHost('localhost', "/{$link->slug}")
            ->assertNotFound();
    }

    public function test_challenge_endpoint_matches_only_the_domain_host_and_token(): void
    {
        $domain = $this->domainFor(User::factory()->create());

        $this->getFromHost(
            $domain->hostname,
            "/.well-known/linkpilot-domain/{$domain->verification_token}",
        )
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee($domain->verification_token);

        $this->getFromHost(
            'unknown.example.test',
            "/.well-known/linkpilot-domain/{$domain->verification_token}",
        )->assertNotFound();
    }

    public function test_domain_deletion_requires_typed_confirmation_and_clears_assignments(): void
    {
        $user = $this->userWithPlan('pro');
        $domain = $this->domainFor($user, ['status' => 'active']);
        $link = $this->linkFor($user, 'delete-domain-link');

        $this->actingAs($user)
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link, [
                'custom_domain_id' => $domain->id,
            ]))
            ->assertRedirect('/dashboard/links');

        $this->actingAs($user)
            ->delete("/dashboard/custom-domains/{$domain->id}", ['confirmation' => 'wrong.example.test'])
            ->assertSessionHasErrors('confirmation');

        $this->assertSame($domain->id, $link->refresh()->custom_domain_id);

        $this->actingAs($user)
            ->delete("/dashboard/custom-domains/{$domain->id}", ['confirmation' => $domain->hostname])
            ->assertRedirect('/dashboard/custom-domains');

        $this->assertModelMissing($domain);
        $this->assertNull($link->refresh()->custom_domain_id);

        $this->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get("/{$link->slug}")
            ->assertRedirect($link->destination_url);
    }

    public function test_all_retained_domains_count_for_plan_usage_and_qr_uses_the_assigned_canonical_host(): void
    {
        $user = $this->userWithPlan('pro');
        $domain = $this->domainFor($user, ['status' => 'active']);
        $this->domainFor($user, ['hostname' => 'retained.example.test', 'status' => 'failed']);
        $this->domainFor($user, ['hostname' => 'pending.example.test', 'status' => 'pending_dns']);
        $link = $this->linkFor($user, 'custom-domain-qr');

        $this->actingAs($user)
            ->patch("/dashboard/links/{$link->id}", $this->linkPayload($link, [
                'custom_domain_id' => $domain->id,
            ]))
            ->assertRedirect('/dashboard/links');

        $this->withoutVite();

        $this->actingAs($user)
            ->get('/account-settings/billing')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.domains.used', 3)
                ->where('usage.domains.limit', 3));

        $this->actingAs($user)
            ->post('/dashboard/custom-domains', ['hostname' => 'over-plan.example.test'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(
            "https://{$domain->hostname}/{$link->slug}",
            app(PublicLinkHostResolver::class)->canonicalUrl($link->fresh('customDomain')),
        );

        $this->actingAs($user)
            ->get("/dashboard/links/{$link->id}/qr-code.svg")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);
    }

    /**
     * @return array{0: DnsTxtLookup, 1: HttpsDomainChallengeProbe}
     */
    private function fakeVerificationBoundaries(): array
    {
        $dns = new class extends DnsTxtLookup
        {
            /** @var list<string> */
            public array $records = [];

            public ?Closure $beforeLookup = null;

            public ?RuntimeException $exception = null;

            public function recordsFor(string $hostname): array
            {
                if ($this->beforeLookup !== null) {
                    $callback = $this->beforeLookup;
                    $this->beforeLookup = null;
                    $callback();
                }

                if ($this->exception !== null) {
                    throw $this->exception;
                }

                return $this->records;
            }
        };
        $https = new class extends HttpsDomainChallengeProbe
        {
            public bool $matches = false;

            public ?string $hostname = null;

            public ?string $token = null;

            public function respondsWithToken(string $hostname, string $token): bool
            {
                $this->hostname = $hostname;
                $this->token = $token;

                return $this->matches;
            }
        };

        $this->app->instance(DnsTxtLookup::class, $dns);
        $this->app->instance(HttpsDomainChallengeProbe::class, $https);

        return [$dns, $https];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function domainFor(User $user, array $attributes = []): CustomDomain
    {
        $workspace = $user->currentWorkspace()->firstOrFail();

        return $workspace->customDomains()->create(array_merge([
            'user_id' => $user->id,
            'hostname' => 'go.example.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'pending_dns',
        ], $attributes));
    }

    private function linkFor(User $user, string $slug): Link
    {
        $workspace = $user->currentWorkspace()->firstOrFail();

        return $workspace->links()->create([
            'user_id' => $user->id,
            'slug' => $slug,
            'destination_url' => "https://destination.example/{$slug}",
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function linkPayload(Link $link, array $overrides = []): array
    {
        return array_merge([
            'destination_url' => $link->destination_url,
            'title' => $link->title ?? '',
            'slug' => $link->slug,
            'is_active' => $link->is_active,
            'folder_id' => $link->folder_id,
            'custom_domain_id' => $link->custom_domain_id,
            'available_from' => $link->available_from?->toISOString(),
            'expires_at' => $link->expires_at?->toISOString(),
            'access_code' => '',
            'clear_access_code' => false,
        ], $overrides);
    }

    private function getFromHost(string $host, string $uri)
    {
        $url = app('url');
        $url->forceRootUrl("http://{$host}");

        try {
            return $this->get($uri);
        } finally {
            $url->forceRootUrl((string) config('app.url'));
        }
    }

    private function userWithPlan(string $planCode): User
    {
        $user = User::factory()->create();
        $plan = Plan::query()->where('code', $planCode)->sole();

        app(WorkspaceUsage::class)->assignPlan($user->personalWorkspace()->firstOrFail(), $plan);

        return $user;
    }
}
