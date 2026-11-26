<?php

$yesNo = static fn (string $label): array => [
    'label' => $label,
    'type' => 'bool',
    'default' => false,
    'rules' => ['boolean'],
];

return [
    'controls' => [
        'bool' => 'switch',
        'email' => 'input',
        'enum' => 'select',
        'int' => 'input',
        'int_list' => 'combobox',
        'list' => 'checkbox-group',
        'media' => 'asset-combobox',
        'string' => 'input',
        'text' => 'textarea',
        'timezone' => 'combobox',
        'url' => 'input',
    ],
    'sections' => [
        'general' => [
            'label' => 'General',
            'description' => 'Site identity and dashboard defaults. APP_URL remains environment-owned.',
            'category' => 'platform',
            'groups' => [
                'identity' => [
                    'label' => 'Identity',
                    'description' => 'Platform name, support contact, and dashboard entry point.',
                    'fields' => ['site_name', 'support_email', 'dashboard_home'],
                ],
                'brand-assets' => [
                    'label' => 'Brand assets',
                    'description' => 'Logos and favicon used across public and authenticated surfaces.',
                    'fields' => ['logo_light_id', 'logo_dark_id', 'compact_logo_id', 'favicon_id'],
                ],
            ],
            'fields' => [
                'site_name' => ['label' => 'Site name', 'type' => 'string', 'default' => 'LinkPilot', 'rules' => ['required', 'string', 'max:120']],
                'support_email' => ['label' => 'Support email', 'type' => 'email', 'default' => 'support@example.test', 'rules' => ['required', 'email', 'max:255']],
                'dashboard_home' => ['label' => 'Dashboard home', 'type' => 'enum', 'default' => 'links', 'options' => ['links' => 'Short links', 'analytics' => 'Analytics'], 'rules' => ['required', 'in:links,analytics']],
                'logo_light_id' => ['label' => 'Light logo', 'type' => 'media', 'asset' => true, 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'logo_dark_id' => ['label' => 'Dark logo', 'type' => 'media', 'asset' => true, 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'compact_logo_id' => ['label' => 'Compact logo', 'type' => 'media', 'asset' => true, 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'favicon_id' => ['label' => 'Favicon', 'type' => 'media', 'asset' => true, 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
            ],
        ],
        'links' => [
            'label' => 'Links',
            'description' => 'Short-link defaults and feature enablement.',
            'category' => 'product',
            'groups' => [
                'defaults' => [
                    'label' => 'Defaults',
                    'description' => 'Slug and delivery defaults for newly created links.',
                    'fields' => ['default_delivery', 'slug_min', 'slug_max', 'slug_charset', 'splash_seconds'],
                ],
                'capabilities' => [
                    'label' => 'Capabilities',
                    'description' => 'Control which optional link features can be assigned.',
                    'fields' => ['targeting_enabled', 'pixels_enabled', 'domains_enabled'],
                ],
                'safeguards' => [
                    'label' => 'Safeguards',
                    'description' => 'Central destination and keyword restrictions.',
                    'fields' => ['keyword_blocklist', 'domain_blocklist'],
                ],
            ],
            'fields' => [
                'default_delivery' => ['label' => 'Default delivery', 'type' => 'enum', 'default' => 'direct', 'options' => ['direct' => 'Direct', 'splash' => 'Splash'], 'rules' => ['required', 'in:direct,splash']],
                'slug_min' => ['label' => 'Minimum slug length', 'type' => 'int', 'default' => 4, 'rules' => ['required', 'integer', 'between:2,32']],
                'slug_max' => ['label' => 'Maximum slug length', 'type' => 'int', 'default' => 32, 'rules' => ['required', 'integer', 'between:2,64']],
                'slug_charset' => ['label' => 'Slug character set', 'type' => 'enum', 'default' => 'alphanumeric_dash', 'options' => ['alphanumeric' => 'Alphanumeric', 'alphanumeric_dash' => 'Alphanumeric and dash'], 'rules' => ['required', 'in:alphanumeric,alphanumeric_dash']],
                'targeting_enabled' => ['label' => 'Targeting rules', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'pixels_enabled' => ['label' => 'Tracking pixels', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'domains_enabled' => ['label' => 'Custom domains', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'splash_seconds' => ['label' => 'Splash seconds', 'type' => 'int', 'default' => 3, 'rules' => ['required', 'integer', 'between:0,30']],
                'keyword_blocklist' => ['label' => 'Blocked keywords', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:5000'], 'help' => 'One keyword per line.'],
                'domain_blocklist' => ['label' => 'Blocked domains', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:5000'], 'help' => 'One hostname per line.'],
            ],
        ],
        'biolinks' => [
            'label' => 'Biolinks',
            'description' => 'Bio-page branding and allowlisted blocks.',
            'category' => 'product',
            'groups' => [
                'branding' => [
                    'label' => 'Branding',
                    'description' => 'Public attribution and its optional image.',
                    'fields' => ['branding_enabled', 'branding_media_id'],
                ],
                'content' => [
                    'label' => 'Available content',
                    'description' => 'Block types administrators allow creators to add.',
                    'fields' => ['available_blocks'],
                ],
            ],
            'fields' => [
                'branding_enabled' => $yesNo('Bio branding'),
                'branding_media_id' => ['label' => 'Branding image', 'type' => 'media', 'asset' => true, 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'available_blocks' => ['label' => 'Available blocks', 'type' => 'list', 'default' => ['link', 'text', 'image', 'social', 'embed'], 'options' => ['link' => 'Link', 'text' => 'Text', 'image' => 'Image', 'social' => 'Social links', 'embed' => 'Embed'], 'rules' => ['array'], 'itemRules' => ['in:link,text,image,social,embed']],
            ],
        ],
        'landing' => [
            'label' => 'Landing page',
            'description' => 'Homepage routing and call-to-action visibility.',
            'category' => 'platform',
            'groups' => [
                'destination' => [
                    'label' => 'Homepage',
                    'description' => 'Choose the public destination for the root URL.',
                    'fields' => ['homepage'],
                ],
                'calls-to-action' => [
                    'label' => 'Calls to action',
                    'description' => 'Control which public entry points are promoted.',
                    'fields' => ['pricing_enabled', 'contact_enabled', 'registration_enabled'],
                ],
            ],
            'fields' => [
                'homepage' => ['label' => 'Homepage', 'type' => 'enum', 'default' => 'home', 'options' => ['home' => 'Home', 'pricing' => 'Pricing', 'login' => 'Login'], 'rules' => ['required', 'in:home,pricing,login']],
                'pricing_enabled' => ['label' => 'Pricing CTA', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'contact_enabled' => ['label' => 'Contact CTA', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'registration_enabled' => $yesNo('Registration CTA'),
            ],
        ],
        'themes' => [
            'label' => 'Themes',
            'description' => 'Platform default scheme, accent, and radius. User preference overrides the scheme.',
            'category' => 'platform',
            'groups' => [
                'appearance' => [
                    'label' => 'Default appearance',
                    'description' => 'Defaults used before a person saves an appearance preference.',
                    'fields' => ['default_scheme', 'accent', 'radius'],
                ],
            ],
            'fields' => [
                'default_scheme' => ['label' => 'Default scheme', 'type' => 'enum', 'default' => 'system', 'options' => ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'], 'rules' => ['required', 'in:system,light,dark']],
                'accent' => ['label' => 'Accent palette', 'type' => 'enum', 'default' => 'blue', 'options' => ['blue' => 'Blue', 'teal' => 'Teal', 'violet' => 'Violet', 'rose' => 'Rose', 'orange' => 'Orange'], 'rules' => ['required', 'in:blue,teal,violet,rose,orange']],
                'radius' => ['label' => 'Corner radius', 'type' => 'enum', 'default' => 'md', 'options' => ['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'], 'rules' => ['required', 'in:none,sm,md,lg']],
            ],
        ],
        'menus' => [
            'label' => 'Menus',
            'description' => 'Known route IDs and published page IDs only. Arbitrary URLs and markup are not allowed.',
            'category' => 'platform',
            'groups' => [
                'header' => [
                    'label' => 'Header menu',
                    'description' => 'Ordered application routes and published pages.',
                    'fields' => ['header_items', 'header_page_ids'],
                ],
                'footer' => [
                    'label' => 'Footer menu',
                    'description' => 'Ordered application routes and published pages.',
                    'fields' => ['footer_items', 'footer_page_ids'],
                ],
            ],
            'fields' => [
                'header_items' => ['label' => 'Header items', 'type' => 'list', 'default' => ['home', 'pricing', 'login', 'register'], 'options' => ['home' => 'Home', 'pricing' => 'Pricing', 'contact' => 'Contact', 'login' => 'Login', 'register' => 'Register'], 'rules' => ['array'], 'itemRules' => ['in:home,pricing,contact,login,register']],
                'footer_items' => ['label' => 'Footer items', 'type' => 'list', 'default' => ['contact', 'login'], 'options' => ['home' => 'Home', 'pricing' => 'Pricing', 'contact' => 'Contact', 'login' => 'Login', 'register' => 'Register'], 'rules' => ['array'], 'itemRules' => ['in:home,pricing,contact,login,register']],
                'header_page_ids' => ['label' => 'Header pages', 'type' => 'int_list', 'default' => [], 'rules' => ['array'], 'itemRules' => ['integer', 'exists:custom_pages,id'], 'help' => 'Search published pages, then use the arrows to change their order.'],
                'footer_page_ids' => ['label' => 'Footer pages', 'type' => 'int_list', 'default' => [], 'rules' => ['array'], 'itemRules' => ['integer', 'exists:custom_pages,id'], 'help' => 'Search published pages, then use the arrows to change their order.'],
            ],
        ],
        'subscriptions' => [
            'route_key' => 'payment-gateways',
            'label' => 'Payment gateways',
            'description' => 'Billing availability, checkout policy, and encrypted payment provider credentials.',
            'category' => 'revenue',
            'groups' => [
                'availability' => [
                    'label' => 'Billing availability',
                    'description' => 'Pricing and checkout capabilities.',
                    'fields' => ['billing_enabled', 'annual_display', 'promotion_codes'],
                ],
                'checkout' => [
                    'label' => 'Checkout policy',
                    'description' => 'Currency, address collection, and tax acknowledgement.',
                    'fields' => ['currency', 'billing_address_collection', 'tax_policy_acknowledged'],
                ],
                'stripe' => [
                    'label' => 'Stripe',
                    'description' => 'Publishable key and encrypted server credentials.',
                    'fields' => ['stripe_publishable_key', 'stripe_secret', 'stripe_webhook_secret'],
                ],
                'paypal' => [
                    'label' => 'PayPal',
                    'description' => 'PayPal application credentials and webhook ID.',
                    'fields' => ['paypal_mode', 'paypal_client_id', 'paypal_secret', 'paypal_webhook_id'],
                ],
            ],
            'fields' => [
                'billing_enabled' => ['label' => 'Billing enabled', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'annual_display' => ['label' => 'Annual pricing display', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'promotion_codes' => $yesNo('Allow promotion codes'),
                'billing_address_collection' => ['label' => 'Billing address collection', 'type' => 'enum', 'default' => 'auto', 'options' => ['auto' => 'Automatic', 'required' => 'Required'], 'rules' => ['required', 'in:auto,required']],
                'stripe_publishable_key' => ['label' => 'Publishable key', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255', 'regex:/\Apk_(?:test|live)_[A-Za-z0-9_]+\z/']],
                'stripe_secret' => ['label' => 'Secret key', 'type' => 'string', 'default' => null, 'secret' => true, 'autocomplete' => 'new-password', 'rules' => ['nullable', 'string', 'max:255', 'regex:/\Ask_(?:test|live)_[A-Za-z0-9_]+\z/']],
                'stripe_webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'string', 'default' => null, 'secret' => true, 'autocomplete' => 'new-password', 'rules' => ['nullable', 'string', 'max:255', 'regex:/\Awhsec_[A-Za-z0-9_]+\z/']],
                'paypal_mode' => ['label' => 'Mode', 'type' => 'enum', 'default' => 'sandbox', 'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'], 'rules' => ['required', 'in:sandbox,live']],
                'paypal_client_id' => ['label' => 'Client ID', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'paypal_secret' => ['label' => 'Secret', 'type' => 'string', 'default' => null, 'secret' => true, 'autocomplete' => 'new-password', 'rules' => ['nullable', 'string', 'max:255']],
                'paypal_webhook_id' => ['label' => 'Webhook ID', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'currency' => ['label' => 'Currency', 'type' => 'string', 'default' => 'usd', 'rules' => ['required', 'string', 'regex:/\A[a-z]{3}\z/']],
                'tax_policy_acknowledged' => ['label' => 'Tax policy acknowledged', 'type' => 'bool', 'control' => 'checkbox', 'default' => false, 'rules' => ['boolean']],
            ],
        ],
        'localization' => [
            'label' => 'Localization',
            'description' => 'Default locale and timezone for runtime fallback.',
            'category' => 'platform',
            'groups' => [
                'language' => [
                    'label' => 'Language',
                    'description' => 'Database translation and locale fallback.',
                    'fields' => ['translations_enabled', 'default_locale'],
                ],
                'region' => [
                    'label' => 'Region and time',
                    'description' => 'Runtime timezone for server-side consumers.',
                    'fields' => ['timezone'],
                ],
            ],
            'fields' => [
                'translations_enabled' => $yesNo('Database translations'),
                'default_locale' => ['label' => 'Default locale', 'type' => 'string', 'control' => 'combobox', 'default' => 'en', 'rules' => ['required', 'string', 'max:10']],
                'timezone' => ['label' => 'Timezone', 'type' => 'timezone', 'default' => 'UTC', 'rules' => ['required', 'timezone:all']],
            ],
        ],
        'authentication' => [
            'label' => 'Authentication',
            'description' => 'Registration, Google OAuth, session, and blocked-domain policy.',
            'category' => 'access',
            'groups' => [
                'registration' => [
                    'label' => 'Registration',
                    'description' => 'New-account access and blocked domains.',
                    'fields' => ['registration_enabled', 'blocked_email_domains'],
                ],
                'google' => [
                    'label' => 'Google sign-in',
                    'description' => 'Encrypted provider configuration and account-linking policy.',
                    'fields' => ['google_enabled', 'google_require_existing', 'google_client_id', 'google_client_secret'],
                ],
                'sessions' => [
                    'label' => 'Sessions',
                    'description' => 'Active-session policy.',
                    'fields' => ['single_session'],
                ],
            ],
            'fields' => [
                'registration_enabled' => ['label' => 'Public registration', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'google_enabled' => $yesNo('Google sign-in'),
                'google_require_existing' => $yesNo('Google requires an existing account'),
                'google_client_id' => ['label' => 'Google client ID', 'type' => 'string', 'default' => '', 'autocomplete' => 'off', 'rules' => ['nullable', 'string', 'max:255', 'regex:/\A[A-Za-z0-9_-]+\.apps\.googleusercontent\.com\z/']],
                'google_client_secret' => ['label' => 'Google client secret', 'type' => 'string', 'default' => null, 'secret' => true, 'autocomplete' => 'new-password', 'rules' => ['nullable', 'string', 'max:1024']],
                'single_session' => $yesNo('Single active session'),
                'blocked_email_domains' => ['label' => 'Blocked email domains', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:5000'], 'help' => 'One email domain per line.'],
            ],
        ],
        'uploading' => [
            'label' => 'Uploading',
            'description' => 'Storage destination, S3 credentials, hard-capped sizes, and retention. MIME types remain a code allowlist.',
            'category' => 'product',
            'groups' => [
                'storage' => [
                    'label' => 'Storage',
                    'description' => 'Destination for newly uploaded private media.',
                    'fields' => ['disk'],
                ],
                's3' => [
                    'label' => 'S3 storage',
                    'description' => 'Credentials for the s3 disk. Empty fields fall back to the deployment environment.',
                    'fields' => ['s3_access_key_id', 's3_secret_access_key', 's3_region', 's3_bucket', 's3_endpoint', 's3_use_path_style_endpoint'],
                ],
                'limits' => [
                    'label' => 'Upload limits',
                    'description' => 'Soft limits bounded by application hard caps.',
                    'fields' => ['max_image_mb', 'max_file_mb'],
                ],
                'retention' => [
                    'label' => 'Retention',
                    'description' => 'Lifetime for expiring content media.',
                    'fields' => ['retention_days'],
                ],
            ],
            'fields' => [
                'disk' => ['label' => 'Storage disk', 'type' => 'string', 'control' => 'combobox', 'default' => 'local', 'help' => 'Applies to new uploads only; existing media keeps its recorded disk.', 'rules' => ['required', 'string', 'max:100']],
                's3_access_key_id' => ['label' => 'Access key ID', 'type' => 'string', 'default' => '', 'visibleWhen' => ['disk' => 's3'], 'autocomplete' => 'off', 'help' => 'Falls back to AWS_ACCESS_KEY_ID when empty.', 'rules' => ['nullable', 'string', 'max:255']],
                's3_secret_access_key' => ['label' => 'Secret access key', 'type' => 'string', 'default' => null, 'secret' => true, 'visibleWhen' => ['disk' => 's3'], 'autocomplete' => 'new-password', 'help' => 'Falls back to AWS_SECRET_ACCESS_KEY when not set.', 'rules' => ['nullable', 'string', 'max:1024']],
                's3_region' => ['label' => 'Region', 'type' => 'string', 'default' => '', 'visibleWhen' => ['disk' => 's3'], 'placeholder' => 'us-east-1', 'help' => 'Required by AWS; use auto for Cloudflare R2.', 'rules' => ['nullable', 'string', 'max:64']],
                's3_bucket' => ['label' => 'Bucket', 'type' => 'string', 'default' => '', 'visibleWhen' => ['disk' => 's3'], 'help' => 'Falls back to AWS_BUCKET when empty.', 'rules' => ['nullable', 'string', 'max:255']],
                's3_endpoint' => ['label' => 'Endpoint', 'type' => 'url', 'default' => '', 'visibleWhen' => ['disk' => 's3'], 'placeholder' => 'https://s3.example.com', 'help' => 'Leave empty for AWS; set it for S3-compatible providers and MinIO.', 'rules' => ['nullable', 'url', 'max:255']],
                's3_use_path_style_endpoint' => ['label' => 'Path-style endpoint', 'type' => 'bool', 'default' => false, 'visibleWhen' => ['disk' => 's3'], 'help' => 'Enable for MinIO and providers that require path-style requests.', 'rules' => ['boolean']],
                'max_image_mb' => ['label' => 'Maximum image MB', 'type' => 'int', 'default' => 5, 'rules' => ['required', 'integer', 'between:1,5']],
                'max_file_mb' => ['label' => 'Maximum PDF MB', 'type' => 'int', 'default' => 25, 'rules' => ['required', 'integer', 'between:1,25']],
                'retention_days' => ['label' => 'Retention days', 'type' => 'int', 'default' => 365, 'rules' => ['required', 'integer', 'between:1,3650']],
            ],
        ],
        'email' => [
            'label' => 'Email',
            'description' => 'Sender identity and encrypted SMTP configuration.',
            'category' => 'operations',
            'groups' => [
                'sender' => [
                    'label' => 'Sender',
                    'description' => 'Identity used in outgoing messages.',
                    'fields' => ['sender_name', 'sender_address'],
                ],
                'delivery' => [
                    'label' => 'Delivery',
                    'description' => 'SMTP delivery settings; log keeps messages in the application log.',
                    'fields' => ['mailer', 'smtp_host', 'smtp_port', 'smtp_security', 'smtp_username', 'smtp_password', 'smtp_timeout_seconds', 'smtp_ehlo_domain'],
                ],
            ],
            'fields' => [
                'sender_name' => ['label' => 'Sender name', 'type' => 'string', 'default' => 'LinkPilot', 'autocomplete' => 'organization', 'rules' => ['required', 'string', 'max:120']],
                'sender_address' => ['label' => 'Sender address', 'type' => 'email', 'default' => 'noreply@example.test', 'autocomplete' => 'email', 'rules' => ['required', 'email', 'max:255']],
                'mailer' => ['label' => 'Mailer', 'type' => 'enum', 'default' => 'log', 'options' => ['log' => 'Log', 'smtp' => 'SMTP'], 'rules' => ['required', 'in:log,smtp']],
                'smtp_host' => ['label' => 'SMTP host', 'type' => 'string', 'default' => '', 'visibleWhen' => ['mailer' => 'smtp'], 'rules' => ['nullable', 'string', 'max:255']],
                'smtp_port' => ['label' => 'SMTP port', 'type' => 'int', 'default' => 587, 'visibleWhen' => ['mailer' => 'smtp'], 'rules' => ['required', 'integer', 'between:1,65535']],
                'smtp_security' => ['label' => 'SMTP security', 'type' => 'enum', 'default' => 'starttls', 'visibleWhen' => ['mailer' => 'smtp'], 'options' => ['starttls' => 'STARTTLS', 'tls' => 'Implicit TLS', 'none' => 'None'], 'rules' => ['required', 'in:starttls,tls,none']],
                'smtp_username' => ['label' => 'SMTP username', 'type' => 'string', 'default' => '', 'visibleWhen' => ['mailer' => 'smtp'], 'autocomplete' => 'username', 'rules' => ['nullable', 'string', 'max:255']],
                'smtp_password' => ['label' => 'SMTP password', 'type' => 'string', 'default' => null, 'secret' => true, 'visibleWhen' => ['mailer' => 'smtp'], 'autocomplete' => 'new-password', 'rules' => ['nullable', 'string', 'max:1024']],
                'smtp_timeout_seconds' => ['label' => 'SMTP timeout', 'type' => 'int', 'default' => 60, 'visibleWhen' => ['mailer' => 'smtp'], 'rules' => ['required', 'integer', 'between:1,120']],
                'smtp_ehlo_domain' => ['label' => 'EHLO domain', 'type' => 'string', 'default' => '', 'visibleWhen' => ['mailer' => 'smtp'], 'rules' => ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}\z)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)*[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/']],
            ],
        ],
        'system' => [
            'label' => 'System',
            'description' => 'Operational retention and maintenance messaging. No updater or backend credential editor.',
            'category' => 'operations',
            'groups' => [
                'operations' => [
                    'label' => 'Operations',
                    'description' => 'Retention and platform-wide notices.',
                    'fields' => ['log_retention_days', 'maintenance_banner', 'api_rate_limit_per_minute'],
                ],
                'webhooks' => [
                    'label' => 'Webhook delivery',
                    'description' => 'Retry schedule, transport bounds, and stored response excerpts.',
                    'fields' => ['webhook_retry_delays', 'webhook_connect_timeout_seconds', 'webhook_timeout_seconds', 'webhook_max_attempts', 'webhook_excerpt_bytes'],
                ],
            ],
            'fields' => [
                'log_retention_days' => ['label' => 'Log retention days', 'type' => 'int', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,365']],
                'maintenance_banner' => ['label' => 'Maintenance banner', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:500']],
                'api_rate_limit_per_minute' => ['label' => 'API requests per minute', 'type' => 'int', 'default' => 60, 'rules' => ['required', 'integer', 'between:1,10000']],
                'webhook_retry_delays' => ['label' => 'Retry delays', 'type' => 'int_list', 'control' => 'number-list', 'default' => [60, 300], 'rules' => ['required', 'array', 'between:1,10'], 'itemRules' => ['integer', 'between:1,86400']],
                'webhook_connect_timeout_seconds' => ['label' => 'Connect timeout', 'type' => 'int', 'default' => 3, 'rules' => ['required', 'integer', 'between:1,30']],
                'webhook_timeout_seconds' => ['label' => 'Response timeout', 'type' => 'int', 'default' => 10, 'rules' => ['required', 'integer', 'between:1,120']],
                'webhook_max_attempts' => ['label' => 'Maximum attempts', 'type' => 'int', 'default' => 3, 'rules' => ['required', 'integer', 'between:1,10']],
                'webhook_excerpt_bytes' => ['label' => 'Response excerpt bytes', 'type' => 'int', 'default' => 500, 'rules' => ['required', 'integer', 'between:0,10000']],
            ],
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Consent and analytics retention. Hash/GeoIP readiness is read-only.',
            'category' => 'operations',
            'groups' => [
                'privacy' => [
                    'label' => 'Privacy and retention',
                    'description' => 'Consent and first-party event lifetime.',
                    'fields' => ['consent_enabled', 'retention_days', 'visitor_hash_key', 'visitor_hash_version'],
                ],
                'measurement' => [
                    'label' => 'Public measurement',
                    'description' => 'Allowlisted public analytics identifier.',
                    'fields' => ['ga4_measurement_id'],
                ],
            ],
            'fields' => [
                'consent_enabled' => ['label' => 'Consent required', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'retention_days' => ['label' => 'Retention days', 'type' => 'int', 'default' => 90, 'rules' => ['required', 'integer', 'between:1,365']],
                'ga4_measurement_id' => ['label' => 'GA4 measurement ID', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'regex:/^G-[A-Z0-9]{4,20}$/'], 'help' => 'Validated G- identifier only.'],
                'visitor_hash_key' => ['label' => 'Visitor hash key', 'type' => 'string', 'default' => null, 'secret' => true, 'autocomplete' => 'new-password', 'rules' => ['nullable', 'string', 'between:32,255']],
                'visitor_hash_version' => ['label' => 'Visitor hash version', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9._-]{1,64}\z/']],
            ],
        ],
        'brand-verification' => [
            'label' => 'Brand verification',
            'description' => 'Fixed verification tokens rendered as meta tags. No arbitrary script or HTML.',
            'category' => 'platform',
            'groups' => [
                'google' => [
                    'label' => 'Google',
                    'description' => 'Google Search Console verification.',
                    'fields' => ['google_token'],
                ],
                'bing' => [
                    'label' => 'Bing',
                    'description' => 'Bing Webmaster Tools verification.',
                    'fields' => ['bing_token'],
                ],
                'pinterest' => [
                    'label' => 'Pinterest',
                    'description' => 'Pinterest domain verification.',
                    'fields' => ['pinterest_token'],
                ],
            ],
            'fields' => [
                'google_token' => ['label' => 'Google token', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'bing_token' => ['label' => 'Bing token', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'pinterest_token' => ['label' => 'Pinterest token', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
            ],
        ],
        'captcha' => [
            'label' => 'Captcha',
            'description' => 'Turnstile site/secret configuration. Incomplete configuration disables captcha.',
            'category' => 'access',
            'groups' => [
                'provider' => [
                    'label' => 'Provider',
                    'description' => 'Select the registration challenge provider.',
                    'fields' => ['provider'],
                ],
                'credentials' => [
                    'label' => 'Credentials',
                    'description' => 'Public site key and encrypted server secret.',
                    'fields' => ['site_key', 'secret'],
                ],
                'enforcement' => [
                    'label' => 'Enforcement',
                    'description' => 'Choose whether registration requires a challenge.',
                    'fields' => ['on_registration'],
                ],
            ],
            'fields' => [
                'provider' => ['label' => 'Provider', 'type' => 'enum', 'default' => 'none', 'options' => ['none' => 'None', 'turnstile' => 'Cloudflare Turnstile'], 'rules' => ['required', 'in:none,turnstile']],
                'site_key' => ['label' => 'Site key', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'secret' => ['label' => 'Secret key', 'type' => 'string', 'default' => null, 'secret' => true, 'autocomplete' => 'new-password', 'rules' => ['nullable', 'string', 'max:1024']],
                'on_registration' => $yesNo('Registration protection'),
            ],
        ],
        'gdpr' => [
            'label' => 'GDPR',
            'description' => 'Consent notice and privacy/terms destinations.',
            'category' => 'access',
            'groups' => [
                'consent' => [
                    'label' => 'Consent',
                    'description' => 'Notice copy and placement.',
                    'fields' => ['consent_notice', 'consent_position'],
                ],
                'policies' => [
                    'label' => 'Policy destinations',
                    'description' => 'Published pages take precedence over external URLs.',
                    'fields' => ['privacy_url', 'privacy_page_id', 'terms_url', 'terms_page_id'],
                ],
            ],
            'fields' => [
                'consent_notice' => ['label' => 'Consent notice', 'type' => 'text', 'default' => 'We use privacy-first analytics only with your consent.', 'rules' => ['required', 'string', 'max:2000']],
                'consent_position' => ['label' => 'Consent position', 'type' => 'enum', 'default' => 'banner', 'options' => ['banner' => 'Banner', 'corner' => 'Corner'], 'rules' => ['required', 'in:banner,corner']],
                'privacy_url' => ['label' => 'Privacy URL', 'type' => 'url', 'default' => '', 'rules' => ['nullable', 'url', 'max:500']],
                'terms_url' => ['label' => 'Terms URL', 'type' => 'url', 'default' => '', 'rules' => ['nullable', 'url', 'max:500']],
                'privacy_page_id' => ['label' => 'Privacy page', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:custom_pages,id']],
                'terms_page_id' => ['label' => 'Terms page', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:custom_pages,id']],
            ],
        ],
        'seo' => [
            'label' => 'SEO',
            'description' => 'Structured default and per-public-route metadata. No raw head HTML.',
            'category' => 'platform',
            'groups' => [
                'defaults' => [
                    'label' => 'Defaults',
                    'description' => 'Fallback metadata for public pages.',
                    'fields' => ['default_title', 'default_description'],
                ],
                'home' => [
                    'label' => 'Home page',
                    'description' => 'Metadata specific to the public home page.',
                    'fields' => ['home_title', 'home_description'],
                ],
                'pricing' => [
                    'label' => 'Pricing page',
                    'description' => 'Metadata specific to the pricing page.',
                    'fields' => ['pricing_title', 'pricing_description'],
                ],
                'sharing' => [
                    'label' => 'Social sharing',
                    'description' => 'Default Open Graph image.',
                    'fields' => ['og_media_id'],
                ],
            ],
            'fields' => [
                'default_title' => ['label' => 'Default title', 'type' => 'string', 'default' => 'LinkPilot', 'rules' => ['required', 'string', 'max:160']],
                'default_description' => ['label' => 'Default description', 'type' => 'text', 'default' => 'Short links and pages.', 'rules' => ['required', 'string', 'max:320']],
                'home_title' => ['label' => 'Home title', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:160']],
                'home_description' => ['label' => 'Home description', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:320']],
                'pricing_title' => ['label' => 'Pricing title', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:160']],
                'pricing_description' => ['label' => 'Pricing description', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:320']],
                'og_media_id' => ['label' => 'Default sharing image', 'type' => 'media', 'asset' => true, 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
            ],
        ],
        'ads' => [
            'label' => 'Ads',
            'description' => 'Allowlisted publisher/slot identifiers only. Script textareas are not supported.',
            'category' => 'revenue',
            'groups' => [
                'provider' => [
                    'label' => 'Provider',
                    'description' => 'Allowlisted advertising provider and publisher.',
                    'fields' => ['provider', 'publisher_id'],
                ],
                'placements' => [
                    'label' => 'Placements',
                    'description' => 'Provider slot identifiers for supported surfaces.',
                    'fields' => ['header_slot', 'content_slot'],
                ],
                'privacy' => [
                    'label' => 'Privacy',
                    'description' => 'Require consent before advertising loads.',
                    'fields' => ['consent_required'],
                ],
            ],
            'fields' => [
                'provider' => ['label' => 'Provider', 'type' => 'enum', 'default' => 'none', 'options' => ['none' => 'None', 'adsense' => 'Google AdSense'], 'rules' => ['required', 'in:none,adsense']],
                'publisher_id' => ['label' => 'Publisher ID', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'regex:/^ca-pub-[0-9]{10,20}$/']],
                'header_slot' => ['label' => 'Header slot ID', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'regex:/^[0-9]{4,20}$/']],
                'content_slot' => ['label' => 'Content slot ID', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'regex:/^[0-9]{4,20}$/']],
                'consent_required' => $yesNo('Require consent'),
            ],
        ],
    ],
];
