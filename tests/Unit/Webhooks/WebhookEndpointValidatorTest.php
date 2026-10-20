<?php

namespace Tests\Unit\Webhooks;

use App\Services\Webhooks\WebhookEndpointValidator;
use PHPUnit\Framework\TestCase;

class WebhookEndpointValidatorTest extends TestCase
{
    public function test_rejects_non_https_hosts_and_embedded_credentials(): void
    {
        $validator = $this->validatorFor(['8.8.8.8']);

        $this->assertNull($validator->validate('http://hooks.example.test/hook'));
        $this->assertNull($validator->validate('hooks.example.test/hook'));
        $this->assertNull($validator->validate('https://user:pass@hooks.example.test/hook'));
        $this->assertNull($validator->validate('https:///hook'));
    }

    public function test_rejects_literal_address_hosts_even_when_public(): void
    {
        $validator = $this->validatorFor(['8.8.8.8']);

        $this->assertNull($validator->validate('https://8.8.8.8/hook'));
        $this->assertNull($validator->validate('https://127.0.0.1/hook'));
        $this->assertNull($validator->validate('https://[2606:4700:4700::1111]/hook'));
    }

    public function test_rejects_hosts_that_do_not_resolve(): void
    {
        $this->assertNull($this->validatorFor([])->validate('https://hooks.example.test/hook'));
    }

    public function test_rejects_hosts_resolving_to_non_public_addresses(): void
    {
        $blocked = [
            '0.0.0.0',
            '10.0.0.5',
            '100.64.0.1',
            '127.0.0.1',
            '169.254.1.1',
            '172.16.0.1',
            '192.0.0.1',
            '192.0.2.5',
            '192.88.99.1',
            '192.168.1.1',
            '198.18.0.1',
            '198.51.100.7',
            '203.0.113.10',
            '224.0.0.1',
            '255.255.255.255',
            '::1',
            '::ffff:10.0.0.5',
            '64:ff9b::a00:1',
            '64:ff9b:1::a00:1',
            '100::1',
            '2001::1',
            '2001:db8::1',
            '2002::1',
            'fc00::1',
            'fd00::1',
            'fe80::1',
            'ff02::1',
        ];

        foreach ($blocked as $address) {
            $this->assertNull(
                $this->validatorFor([$address])->validate('https://hooks.example.test/hook'),
                "Expected [$address] to be rejected as a webhook destination.",
            );
        }
    }

    public function test_rejects_when_any_resolved_address_is_non_public(): void
    {
        $this->assertNull(
            $this->validatorFor(['8.8.8.8', '10.0.0.1'])->validate('https://hooks.example.test/hook'),
        );
    }

    public function test_accepts_public_hosts_and_pins_the_resolved_addresses(): void
    {
        $this->assertSame(
            'hooks.example.test:443:8.8.8.8,1.1.1.1',
            $this->validatorFor(['8.8.8.8', '1.1.1.1'])->validate('https://hooks.example.test/hook'),
        );

        $this->assertSame(
            'hooks.example.test:8443:8.8.8.8',
            $this->validatorFor(['8.8.8.8'])->validate('https://hooks.example.test:8443/hook'),
        );

        $this->assertSame(
            'hooks.example.test:443:[2606:4700:4700::1111]',
            $this->validatorFor(['2606:4700:4700::1111'])->validate('https://hooks.example.test/hook'),
        );
    }

    private function validatorFor(array $addresses): WebhookEndpointValidator
    {
        return new class($addresses) extends WebhookEndpointValidator
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
        };
    }
}
