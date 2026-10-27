<?php

return [

    'api' => [
        'rate_limit_per_minute' => max(1, (int) env('API_RATE_LIMIT_PER_MINUTE', 60)),
    ],
    'default_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LINKPILOT_DEFAULT_HOSTS', '')),
    ), static fn (string $host): bool => $host !== '')),

    'reserved_slugs' => [
        '.well-known',
        'account-settings',
        'admin',
        'analytics',
        'api',
        'bio',
        'bio-pages',
        'billing',
        'confirm-password',
        'contact',
        'content',
        'dashboard',
        'email',
        'forgot-password',
        'link-pages',
        'links',
        'login',
        'logout',
        'notifications',
        'overlays',
        'pages',
        'pricing',
        'register',
        'reset-password',
        'settings',
        'stripe',
        'tracking-consent',
        'tracking-pixels',
        'two-factor-challenge',
        'up',
        'user',
        'webhooks',
    ],

    'country_targeting' => [
        'header' => trim((string) env('LINKPILOT_COUNTRY_HEADER', '')) ?: null,
        'trusted_proxy_cidrs' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LINKPILOT_COUNTRY_PROXY_CIDRS', '')),
        ), static fn (string $cidr): bool => $cidr !== '')),
    ],
];
