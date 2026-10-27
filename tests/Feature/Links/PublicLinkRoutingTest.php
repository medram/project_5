<?php

namespace Tests\Feature\Links;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLinkRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_header_is_ignored_when_socket_peer_is_not_trusted(): void
    {
        config()->set('linkpilot.country_targeting.header', 'CF-IPCountry');
        config()->set('linkpilot.country_targeting.trusted_proxy_cidrs', ['10.20.0.0/16']);
        $link = Link::factory()->create(['slug' => 'country-spoof']);
        $this->addRule($link, 1, 'country', 'US', 'https://example.org/untrusted');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.30'])
            ->withHeader('CF-IPCountry', 'US')
            ->withHeader('X-Forwarded-For', '10.20.0.10')
            ->get('/country-spoof')
            ->assertRedirect($link->destination_url);
    }

    public function test_country_targeting_accepts_normalized_header_only_from_trusted_cidr(): void
    {
        config()->set('linkpilot.country_targeting.header', 'CF-IPCountry');
        config()->set('linkpilot.country_targeting.trusted_proxy_cidrs', ['10.20.0.0/16']);
        $link = Link::factory()->create(['slug' => 'country-trusted']);
        $this->addRule($link, 1, 'country', 'US', 'https://example.org/us');

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.255.255'])
            ->withHeader('CF-IPCountry', 'us')
            ->get('/country-trusted')
            ->assertRedirect('https://example.org/us');

        $this->assertDatabaseCount('clicks', 1);
    }

    public function test_unknown_country_and_first_matching_rule_order_are_preserved(): void
    {
        config()->set('linkpilot.country_targeting.header', 'CF-IPCountry');
        config()->set('linkpilot.country_targeting.trusted_proxy_cidrs', ['10.20.0.0/16']);
        $link = Link::factory()->create(['slug' => 'routing-order']);
        $this->addRule($link, 1, 'platform', 'android', 'https://example.org/platform');
        $this->addRule($link, 2, 'device', 'mobile', 'https://example.org/device');
        $this->addRule($link, 3, 'country', 'US', 'https://example.org/country');

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.1.2'])
            ->withHeader('CF-IPCountry', 'US')
            ->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 15; Mobile) Chrome/130.0')
            ->get('/routing-order')
            ->assertRedirect('https://example.org/platform');

        $unknown = Link::factory()->create(['slug' => 'country-unknown']);
        $this->addRule($unknown, 1, 'country', 'US', 'https://example.org/us');
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.1.2'])
            ->withHeader('CF-IPCountry', 'XX')
            ->get('/country-unknown')
            ->assertRedirect($unknown->destination_url);
    }

    public function test_trusted_country_cidr_boundaries_are_inclusive_and_adjacent_peers_are_rejected(): void
    {
        config()->set('linkpilot.country_targeting.header', 'CF-IPCountry');
        config()->set('linkpilot.country_targeting.trusted_proxy_cidrs', ['10.20.0.0/16']);
        $cases = [
            ['peer' => '10.20.0.0', 'matches' => true],
            ['peer' => '10.19.255.255', 'matches' => false],
            ['peer' => '10.21.0.0', 'matches' => false],
        ];

        foreach ($cases as $index => $case) {
            $link = Link::factory()->create(['slug' => "country-boundary-{$index}"]);
            $target = 'https://example.org/trusted';
            $this->addRule($link, 1, 'country', 'US', $target);

            $this->withServerVariables(['REMOTE_ADDR' => $case['peer']])
                ->withHeader('CF-IPCountry', 'us')
                ->get('/'.$link->slug)
                ->assertRedirect($case['matches'] ? $target : $link->destination_url);
        }
    }


    private function addRule(Link $link, int $position, string $type, string $value, string $target): void
    {
        $link->routingRules()->create([
            'workspace_id' => $link->workspace_id,
            'position' => $position,
            'rule_type' => $type,
            'match_value' => $value,
            'target_url' => $target,
        ]);
    }
}
