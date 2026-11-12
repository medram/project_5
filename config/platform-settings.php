<?php

$yesNo = static fn (string $label): array => [
    'label' => $label,
    'type' => 'bool',
    'default' => false,
    'rules' => ['boolean'],
];

return [
    'sections' => [
        'general' => [
            'label' => 'General',
            'description' => 'Site identity and dashboard defaults. APP_URL remains environment-owned.',
            'fields' => [
                'site_name' => ['label' => 'Site name', 'type' => 'string', 'default' => 'LinkPilot', 'rules' => ['required', 'string', 'max:120']],
                'support_email' => ['label' => 'Support email', 'type' => 'email', 'default' => 'support@example.test', 'rules' => ['required', 'email', 'max:255']],
                'dashboard_home' => ['label' => 'Dashboard home', 'type' => 'enum', 'default' => 'links', 'options' => ['links' => 'Short links', 'analytics' => 'Analytics'], 'rules' => ['required', 'in:links,analytics']],
                'logo_light_id' => ['label' => 'Light logo media ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'logo_dark_id' => ['label' => 'Dark logo media ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'compact_logo_id' => ['label' => 'Compact logo media ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'favicon_id' => ['label' => 'Favicon media ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
            ],
        ],
        'links' => [
            'label' => 'Links',
            'description' => 'Short-link defaults and feature enablement.',
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
            'fields' => [
                'branding_enabled' => $yesNo('Bio branding'),
                'branding_media_id' => ['label' => 'Branding media ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
                'available_blocks' => ['label' => 'Available blocks', 'type' => 'list', 'default' => ['links', 'image', 'embed', 'divider'], 'options' => ['links' => 'Links', 'image' => 'Image', 'embed' => 'Embed', 'divider' => 'Divider'], 'rules' => ['array'], 'itemRules' => ['in:links,image,embed,divider']],
                'available_widgets' => ['label' => 'Available widgets', 'type' => 'list', 'default' => ['social', 'tracking'], 'options' => ['social' => 'Social links', 'tracking' => 'Tracking'], 'rules' => ['array'], 'itemRules' => ['in:social,tracking']],
            ],
        ],
        'landing' => [
            'label' => 'Landing page',
            'description' => 'Homepage routing and call-to-action visibility.',
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
            'fields' => [
                'default_scheme' => ['label' => 'Default scheme', 'type' => 'enum', 'default' => 'system', 'options' => ['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'], 'rules' => ['required', 'in:system,light,dark']],
                'accent' => ['label' => 'Accent palette', 'type' => 'enum', 'default' => 'blue', 'options' => ['blue' => 'Blue', 'teal' => 'Teal', 'violet' => 'Violet', 'rose' => 'Rose', 'orange' => 'Orange'], 'rules' => ['required', 'in:blue,teal,violet,rose,orange']],
                'radius' => ['label' => 'Corner radius', 'type' => 'enum', 'default' => 'md', 'options' => ['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'], 'rules' => ['required', 'in:none,sm,md,lg']],
            ],
        ],
        'menus' => [
            'label' => 'Menus',
            'description' => 'Known route IDs and published page IDs only. Arbitrary URLs and markup are not allowed.',
            'fields' => [
                'header_items' => ['label' => 'Header items', 'type' => 'list', 'default' => ['home', 'pricing', 'login', 'register'], 'options' => ['home' => 'Home', 'pricing' => 'Pricing', 'contact' => 'Contact', 'login' => 'Login', 'register' => 'Register'], 'rules' => ['array'], 'itemRules' => ['in:home,pricing,contact,login,register']],
                'footer_items' => ['label' => 'Footer items', 'type' => 'list', 'default' => ['contact', 'login'], 'options' => ['home' => 'Home', 'pricing' => 'Pricing', 'contact' => 'Contact', 'login' => 'Login', 'register' => 'Register'], 'rules' => ['array'], 'itemRules' => ['in:home,pricing,contact,login,register']],
                'header_page_ids' => ['label' => 'Header published page IDs', 'type' => 'int_list', 'default' => [], 'rules' => ['array'], 'itemRules' => ['integer', 'exists:custom_pages,id'], 'help' => 'Comma-separated published custom-page IDs.'],
                'footer_page_ids' => ['label' => 'Footer published page IDs', 'type' => 'int_list', 'default' => [], 'rules' => ['array'], 'itemRules' => ['integer', 'exists:custom_pages,id'], 'help' => 'Comma-separated published custom-page IDs.'],
            ],
        ],
        'subscriptions' => [
            'label' => 'Subscriptions',
            'description' => 'Billing display controls. Stripe credentials remain environment-owned.',
            'fields' => [
                'billing_enabled' => ['label' => 'Billing enabled', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'annual_display' => ['label' => 'Annual pricing display', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'promotion_codes' => $yesNo('Allow promotion codes'),
                'invoice_address' => ['label' => 'Invoice address requirement', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:500']],
                'invoice_notes' => ['label' => 'Invoice notes', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:1000']],
            ],
        ],
        'localization' => [
            'label' => 'Localization',
            'description' => 'Default locale, timezone, and date format for runtime fallback.',
            'fields' => [
                'translations_enabled' => $yesNo('Database translations'),
                'default_locale' => ['label' => 'Default locale', 'type' => 'enum', 'default' => 'en', 'options' => ['en' => 'English', 'fr' => 'French', 'es' => 'Spanish', 'de' => 'German'], 'rules' => ['required', 'in:en,fr,es,de']],
                'timezone' => ['label' => 'Timezone', 'type' => 'timezone', 'default' => 'UTC', 'rules' => ['required', 'timezone:all']],
                'date_format' => ['label' => 'Date format', 'type' => 'enum', 'default' => 'M j, Y', 'options' => ['M j, Y' => 'Jan 1, 2026', 'j M Y' => '1 Jan 2026', 'Y-m-d' => '2026-01-01', 'd/m/Y' => '01/01/2026'], 'rules' => ['required', 'in:M j, Y,j M Y,Y-m-d,d/m/Y']],
            ],
        ],
        'authentication' => [
            'label' => 'Authentication',
            'description' => 'Registration, verification, Google, session, and blocked-domain policy.',
            'fields' => [
                'registration_enabled' => ['label' => 'Public registration', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'email_verification_required' => ['label' => 'Require email verification', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'google_enabled' => $yesNo('Google sign-in'),
                'google_require_existing' => $yesNo('Google requires an existing account'),
                'single_session' => $yesNo('Single active session'),
                'blocked_email_domains' => ['label' => 'Blocked email domains', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:5000'], 'help' => 'One email domain per line.'],
            ],
        ],
        'uploading' => [
            'label' => 'Uploading',
            'description' => 'Configured disk, hard-capped sizes, and retention. MIME types remain a code allowlist.',
            'fields' => [
                'disk' => ['label' => 'Storage disk', 'type' => 'enum', 'default' => 'local', 'options' => ['local' => 'Local private', 'public' => 'Public'], 'rules' => ['required', 'in:local,public']],
                'max_image_mb' => ['label' => 'Maximum image MB', 'type' => 'int', 'default' => 5, 'rules' => ['required', 'integer', 'between:1,5']],
                'max_file_mb' => ['label' => 'Maximum PDF MB', 'type' => 'int', 'default' => 25, 'rules' => ['required', 'integer', 'between:1,25']],
                'retention_days' => ['label' => 'Retention days', 'type' => 'int', 'default' => 365, 'rules' => ['required', 'integer', 'between:1,3650']],
            ],
        ],
        'email' => [
            'label' => 'Email',
            'description' => 'Sender identity and preconfigured mailer. Transport credentials stay environment-owned.',
            'fields' => [
                'sender_name' => ['label' => 'Sender name', 'type' => 'string', 'default' => 'LinkPilot', 'rules' => ['required', 'string', 'max:120']],
                'sender_address' => ['label' => 'Sender address', 'type' => 'email', 'default' => 'noreply@example.test', 'rules' => ['required', 'email', 'max:255']],
                'mailer' => ['label' => 'Mailer', 'type' => 'enum', 'default' => 'log', 'options' => ['log' => 'Log', 'smtp' => 'SMTP', 'array' => 'Array'], 'rules' => ['required', 'in:log,smtp,array']],
                'contact_recipient' => ['label' => 'Contact recipient', 'type' => 'email', 'default' => 'support@example.test', 'rules' => ['required', 'email', 'max:255']],
            ],
        ],
        'system' => [
            'label' => 'System',
            'description' => 'Operational retention and maintenance messaging. No updater or backend credential editor.',
            'fields' => [
                'log_retention_days' => ['label' => 'Log retention days', 'type' => 'int', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,365']],
                'maintenance_banner' => ['label' => 'Maintenance banner', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:500']],
            ],
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Consent and analytics retention. Hash/GeoIP readiness is read-only.',
            'fields' => [
                'consent_enabled' => ['label' => 'Consent required', 'type' => 'bool', 'default' => true, 'rules' => ['boolean']],
                'retention_days' => ['label' => 'Retention days', 'type' => 'int', 'default' => 90, 'rules' => ['required', 'integer', 'between:1,365']],
                'ga4_measurement_id' => ['label' => 'GA4 measurement ID', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'regex:/^G-[A-Z0-9]{4,20}$/'], 'help' => 'Validated G- identifier only.'],
            ],
        ],
        'brand-verification' => [
            'label' => 'Brand verification',
            'description' => 'Fixed verification tokens rendered as meta tags. No arbitrary script or HTML.',
            'fields' => [
                'google_token' => ['label' => 'Google token', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'bing_token' => ['label' => 'Bing token', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'pinterest_token' => ['label' => 'Pinterest token', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
            ],
        ],
        'captcha' => [
            'label' => 'Captcha',
            'description' => 'Turnstile site/secret configuration. Incomplete configuration disables captcha.',
            'fields' => [
                'provider' => ['label' => 'Provider', 'type' => 'enum', 'default' => 'none', 'options' => ['none' => 'None', 'turnstile' => 'Cloudflare Turnstile'], 'rules' => ['required', 'in:none,turnstile']],
                'site_key' => ['label' => 'Site key', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
                'secret' => ['label' => 'Secret key', 'type' => 'string', 'default' => '', 'secret' => true, 'env' => 'services.turnstile.secret', 'rules' => ['nullable', 'string', 'max:255']],
                'on_registration' => $yesNo('Registration protection'),
                'on_public_actions' => $yesNo('Public action protection'),
            ],
        ],
        'gdpr' => [
            'label' => 'GDPR',
            'description' => 'Consent notice, analytics default, and privacy/terms destinations.',
            'fields' => [
                'consent_notice' => ['label' => 'Consent notice', 'type' => 'text', 'default' => 'We use privacy-first analytics only with your consent.', 'rules' => ['required', 'string', 'max:2000']],
                'consent_position' => ['label' => 'Consent position', 'type' => 'enum', 'default' => 'banner', 'options' => ['banner' => 'Banner', 'corner' => 'Corner'], 'rules' => ['required', 'in:banner,corner']],
                'analytics_default_denied' => $yesNo('Default analytics to denied'),
                'privacy_url' => ['label' => 'Privacy URL', 'type' => 'url', 'default' => '', 'rules' => ['nullable', 'url', 'max:500']],
                'terms_url' => ['label' => 'Terms URL', 'type' => 'url', 'default' => '', 'rules' => ['nullable', 'url', 'max:500']],
                'privacy_page_id' => ['label' => 'Privacy page ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:custom_pages,id']],
                'terms_page_id' => ['label' => 'Terms page ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:custom_pages,id']],
            ],
        ],
        'seo' => [
            'label' => 'SEO',
            'description' => 'Structured default and per-public-route metadata. No raw head HTML.',
            'fields' => [
                'default_title' => ['label' => 'Default title', 'type' => 'string', 'default' => 'LinkPilot', 'rules' => ['required', 'string', 'max:160']],
                'default_description' => ['label' => 'Default description', 'type' => 'text', 'default' => 'Short links and pages.', 'rules' => ['required', 'string', 'max:320']],
                'home_title' => ['label' => 'Home title', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:160']],
                'home_description' => ['label' => 'Home description', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:320']],
                'pricing_title' => ['label' => 'Pricing title', 'type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:160']],
                'pricing_description' => ['label' => 'Pricing description', 'type' => 'text', 'default' => '', 'rules' => ['nullable', 'string', 'max:320']],
                'og_media_id' => ['label' => 'Default OG media ID', 'type' => 'media', 'default' => null, 'rules' => ['nullable', 'integer', 'exists:content_media,id']],
            ],
        ],
        'ads' => [
            'label' => 'Ads',
            'description' => 'Allowlisted publisher/slot identifiers only. Script textareas are not supported.',
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