<?php

namespace App\Services\Links;

use App\Services\Settings\PlatformSettings;

class LinkPolicy
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function rejection(string $destinationUrl, ?string $slug = null, ?string $title = null): ?string
    {
        $settings = $this->settings->resolved()['links'] ?? [];
        $host = $this->normalizeHost((string) parse_url($destinationUrl, PHP_URL_HOST));

        foreach ($this->lines((string) ($settings['domain_blocklist'] ?? '')) as $blocked) {
            $blocked = $this->normalizeHost($blocked);

            if ($host !== '' && ($host === $blocked || str_ends_with($host, '.'.$blocked))) {
                return 'This destination domain is blocked by platform policy.';
            }
        }

        $path = strtolower((string) parse_url($destinationUrl, PHP_URL_PATH));
        $haystack = strtolower(implode(' ', array_filter([$slug, $title, $host, $path])));

        foreach ($this->lines((string) ($settings['keyword_blocklist'] ?? '')) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, strtolower($keyword))) {
                return 'This destination is blocked by platform policy.';
            }
        }

        return null;
    }

    private function normalizeHost(string $host): string
    {
        $host = rtrim(strtolower(trim($host)), '.');

        if ($host === '') {
            return '';
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                return strtolower($ascii);
            }
        }

        return $host;
    }

    /** @return list<string> */
    private function lines(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $value) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }
}
