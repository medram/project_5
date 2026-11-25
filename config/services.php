<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => null,
        'client_secret' => null,
        'redirect' => '/auth/google/callback',
    ],

    'paypal' => [
        'mode' => 'sandbox',
        'client_id' => null,
        'secret' => null,
        'webhook_id' => null,
    ],

    'linkpilot' => [
        'analytics' => [
            'privacy_url' => null,
            'hash_key' => null,
            'hash_key_version' => null,
            'retention_days' => 90,
            'geoip_database' => env('ANALYTICS_GEOIP_DATABASE'),
        ],
        'media' => [
            'disk' => null,
            'retention_days' => 365,
            'clamav_path' => env('CLAMSCAN_PATH', 'clamscan'),
        ],
        'webhooks' => [
            'retry_delays' => [60, 300],
            'connect_timeout' => 4,
            'timeout' => 30,
            'max_attempts' => 3,
            'excerpt_bytes' => 500,
        ],
    ],

];
