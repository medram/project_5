<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use Tests\TestCase;

class HostBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://app.linkpilot.test',
            'linkpilot.default_hosts' => ['alias.linkpilot.test'],
        ]);
    }

    public function test_default_and_configured_hosts_serve_normal_web_routes(): void
    {
        $this->getFromHost('app.linkpilot.test', '/login')->assertOk();
        $this->getFromHost('alias.linkpilot.test', '/login')->assertOk();
        $this->getFromHost('ALIAS.LINKPILOT.TEST.', '/login')->assertOk();
        $this->getFromHost('app.linkpilot.test', '/up')->assertOk();
    }

    public function test_unknown_hosts_cannot_serve_web_or_health_routes(): void
    {
        foreach (['/', '/login', '/forgot-password', '/dashboard', '/admin', '/bio/example', '/pages/example', '/up'] as $path) {
            $this->getFromHost('unknown.example.test', $path)->assertNotFound();
        }
    }

    public function test_claimed_custom_hosts_serve_only_link_delivery_and_domain_challenge_routes(): void
    {
        $owner = User::factory()->create();
        $domain = $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'links.customer.test',
            'verification_token' => Str::lower(Str::random(40)),
            'status' => 'pending_dns',
        ]);

        $this->getFromHost(
            $domain->hostname,
            "/.well-known/linkpilot-domain/{$domain->verification_token}",
        )->assertOk()->assertSee($domain->verification_token);

        foreach (['/', '/login', '/forgot-password', '/dashboard', '/admin', '/bio/example', '/pages/example', '/up'] as $path) {
            $this->getFromHost($domain->hostname, $path)->assertNotFound();
        }

        $domain->update(['status' => 'active']);
        $link = $owner->currentWorkspace->links()->create([
            'user_id' => $owner->id,
            'custom_domain_id' => $domain->id,
            'slug' => 'host-boundary-link',
            'destination_url' => 'https://destination.example/path',
            'is_active' => true,
        ]);

        $this->getFromHost($domain->hostname, "/{$link->slug}")
            ->assertRedirect($link->destination_url);

        $this->getFromHost("{$domain->hostname}:8443", "/{$link->slug}")
            ->assertNotFound();
    }

    public function test_active_custom_hosts_serve_the_consent_endpoint_and_nothing_else_does(): void
    {
        $owner = User::factory()->create();
        $domain = $owner->currentWorkspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'links.customer.test',
            'verification_token' => Str::lower(Str::random(40)),
            'status' => 'pending_dns',
        ]);

        foreach (['pending_dns', 'pending_tls', 'failed'] as $status) {
            $domain->update(['status' => $status]);

            $this->postFromHost($domain->hostname, '/tracking-consent', [
                'consent' => 'granted',
                'return_path' => '/bio/consent-creator',
            ])->assertNotFound();
        }

        $domain->update(['status' => 'active']);

        $this->postFromHost($domain->hostname, '/tracking-consent', [
            'consent' => 'granted',
            'return_path' => '/bio/consent-creator',
        ])
            ->assertRedirect('/bio/consent-creator')
            ->assertCookie(TrackingConsent::COOKIE_NAME, 'granted');

        $this->postFromHost("{$domain->hostname}:8443", '/tracking-consent', [
            'consent' => 'granted',
            'return_path' => '/bio/consent-creator',
        ])->assertNotFound();

        $this->postFromHost('unknown.example.test', '/tracking-consent', [
            'consent' => 'granted',
            'return_path' => '/bio/consent-creator',
        ])->assertNotFound();
    }

    private function getFromHost(string $host, string $uri): TestResponse
    {
        $url = app('url');
        $url->forceRootUrl("http://{$host}");

        try {
            return $this->get($uri);
        } finally {
            $url->forceRootUrl((string) config('app.url'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postFromHost(string $host, string $uri, array $data = []): TestResponse
    {
        $url = app('url');
        $url->forceRootUrl("http://{$host}");

        try {
            return $this->post($uri, $data);
        } finally {
            $url->forceRootUrl((string) config('app.url'));
        }
    }
}
