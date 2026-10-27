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

    'linkpilot' => [
        'analytics' => [
            'privacy_url' => env('PUBLIC_PRIVACY_URL'),
            'hash_key' => env('ANALYTICS_HASH_KEY'),
            'hash_key_version' => env('ANALYTICS_HASH_KEY_VERSION'),
            'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),
            'geoip_database' => env('ANALYTICS_GEOIP_DATABASE'),
        ],
        'media' => [
            'disk' => env('LINKPILOT_MEDIA_DISK'),
            'retention_days' => (int) env('CONTENT_MEDIA_RETENTION_DAYS', 365),
            'clamav_path' => env('CLAMSCAN_PATH', 'clamscan'),
        ],
        'webhooks' => [
            'retry_delays' => array_map('intval', explode(',', (string) env('WEBHOOK_RETRY_DELAYS', '60,300'))),
            'connect_timeout' => (int) env('WEBHOOK_CONNECT_TIMEOUT', 3),
            'timeout' => (int) env('WEBHOOK_TIMEOUT', 10),
            'max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 3),
            'excerpt_bytes' => (int) env('WEBHOOK_EXCERPT_BYTES', 500),
        ],
    ],

];
