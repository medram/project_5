<?php

namespace App\Services\Settings;

use Illuminate\Support\Facades\Schema;

class PlatformSettingsReadiness
{
    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * @return array{state: string, items: list<array{label: string, state: string, detail: string}>}
     */
    public function forSection(string $section): array
    {
        $resolved = $this->settings->resolved();
        $items = match ($section) {
            'email' => $this->email($resolved),
            'subscriptions' => $this->subscriptions($resolved),
            'authentication' => $this->authentication($resolved),
            'captcha' => $this->captcha($resolved),
            'analytics' => $this->analytics($resolved),
            'uploading' => $this->uploading($resolved),
            'system' => $this->deployment(),
            default => [['label' => 'Configuration', 'state' => 'ready', 'detail' => 'Database-backed settings are available.']],
        };

        $states = array_column($items, 'state');
        $state = in_array('incomplete', $states, true)
            ? 'incomplete'
            : (in_array('ready', $states, true) ? 'ready' : 'disabled');

        return ['state' => $state, 'items' => $items];
    }

    /** @param array<string, mixed> $resolved */
    private function email(array $resolved): array
    {
        $email = $resolved['email'] ?? [];

        if (($email['mailer'] ?? 'log') !== 'smtp') {
            return [['label' => 'Mail transport', 'state' => 'disabled', 'detail' => 'Outgoing mail is written to the application log.']];
        }

        $items = [
            $this->requirement('SMTP host', is_string($email['smtp_host'] ?? null) && $email['smtp_host'] !== ''),
            $this->requirement('SMTP port', is_int($email['smtp_port'] ?? null) && $email['smtp_port'] > 0),
            $this->requirement('SMTP credentials', filled($email['smtp_username'] ?? null) === ($this->settings->secret('email', 'smtp_password') !== null)),
        ];

        return $items;
    }

    /** @param array<string, mixed> $resolved */
    private function subscriptions(array $resolved): array
    {
        $subscriptions = $resolved['subscriptions'] ?? [];

        if (! (bool) ($subscriptions['billing_enabled'] ?? false)) {
            return [['label' => 'Billing', 'state' => 'disabled', 'detail' => 'Billing is disabled; pricing remains informational.']];
        }

        $clientId = filled($subscriptions['paypal_client_id'] ?? null);
        $secret = $this->settings->secret('subscriptions', 'paypal_secret') !== null;
        $webhookId = filled($subscriptions['paypal_webhook_id'] ?? null);
        $paypalConfigured = $clientId || $secret || $webhookId || ($subscriptions['paypal_mode'] ?? 'sandbox') !== 'sandbox';
        $stripeConfigured = filled($subscriptions['stripe_publishable_key'] ?? null)
            || $this->settings->secret('subscriptions', 'stripe_secret') !== null
            || $this->settings->secret('subscriptions', 'stripe_webhook_secret') !== null;

        $items = ! $stripeConfigured && $paypalConfigured
            ? [
                ['label' => 'Stripe', 'state' => 'disabled', 'detail' => 'Not configured.'],
                $this->requirement('Tax policy acknowledgement', (bool) ($subscriptions['tax_policy_acknowledged'] ?? false)),
            ]
            : [
                $this->requirement('Publishable key', is_string($subscriptions['stripe_publishable_key'] ?? null) && $subscriptions['stripe_publishable_key'] !== ''),
                $this->requirement('Secret key', $this->settings->secret('subscriptions', 'stripe_secret') !== null),
                $this->requirement('Webhook secret', $this->settings->secret('subscriptions', 'stripe_webhook_secret') !== null),
                $this->requirement('Tax policy acknowledgement', (bool) ($subscriptions['tax_policy_acknowledged'] ?? false)),
                $this->stripeMode($subscriptions),
                ['label' => 'Webhook URL', 'state' => 'ready', 'detail' => route('cashier.webhook')],
            ];

        if (! $clientId && ! $secret && ! $webhookId && ($subscriptions['paypal_mode'] ?? 'sandbox') === 'sandbox') {
            $items[] = ['label' => 'PayPal', 'state' => 'disabled', 'detail' => 'Not configured.'];
        } else {
            $items[] = $this->requirement('PayPal client ID', $clientId);
            $items[] = $this->requirement('PayPal secret', $secret);
            $items[] = $this->requirement('PayPal webhook ID', $webhookId);
        }

        $items[] = ['label' => 'PayPal webhook URL', 'state' => 'ready', 'detail' => rtrim((string) config('app.url'), '/').route('billing.paypal.webhook', [], false)];

        return $items;
    }

    /** @param array<string, mixed> $subscriptions
     * @return array{label: string, state: string, detail: string}
     */
    private function stripeMode(array $subscriptions): array
    {
        $publishableKey = $subscriptions['stripe_publishable_key'] ?? null;
        $secretKey = $this->settings->secret('subscriptions', 'stripe_secret');
        $publishableMode = is_string($publishableKey)
            ? (str_starts_with($publishableKey, 'pk_test_') ? 'test' : (str_starts_with($publishableKey, 'pk_live_') ? 'live' : null))
            : null;
        $secretMode = is_string($secretKey)
            ? (str_starts_with($secretKey, 'sk_test_') ? 'test' : (str_starts_with($secretKey, 'sk_live_') ? 'live' : null))
            : null;

        if ($publishableMode === null && $secretMode === null) {
            return ['label' => 'Stripe mode', 'state' => 'disabled', 'detail' => 'Not configured.'];
        }

        if ($publishableMode === null || $secretMode === null || $publishableMode !== $secretMode) {
            return ['label' => 'Stripe mode', 'state' => 'incomplete', 'detail' => 'Publishable and secret keys must use the same test or live mode.'];
        }

        return ['label' => 'Stripe mode', 'state' => 'ready', 'detail' => ucfirst($publishableMode).' mode'];
    }

    /** @param array<string, mixed> $resolved */
    private function authentication(array $resolved): array
    {
        $authentication = $resolved['authentication'] ?? [];

        if (! (bool) ($authentication['google_enabled'] ?? false)) {
            return [['label' => 'Google sign-in', 'state' => 'disabled', 'detail' => 'Google sign-in is disabled.']];
        }

        return [
            $this->requirement('Google client ID', filled($authentication['google_client_id'] ?? null)),
            $this->requirement('Google client secret', $this->settings->secret('authentication', 'google_client_secret') !== null),
        ];
    }

    /** @param array<string, mixed> $resolved */
    private function captcha(array $resolved): array
    {
        $captcha = $resolved['captcha'] ?? [];

        if (($captcha['provider'] ?? 'none') !== 'turnstile') {
            return [['label' => 'Captcha', 'state' => 'disabled', 'detail' => 'No registration captcha is enabled.']];
        }

        return [
            $this->requirement('Site key', filled($captcha['site_key'] ?? null)),
            $this->requirement('Secret key', $this->settings->secret('captcha', 'secret') !== null),
            $this->requirement('Registration protection', (bool) ($captcha['on_registration'] ?? false)),
        ];
    }

    /** @param array<string, mixed> $resolved */
    private function analytics(array $resolved): array
    {
        return [
            $this->requirement('Consent destination', filled($this->settings->publicPayload()['gdpr']['privacyUrl'] ?? null)),
            $this->requirement('Visitor hash key', $this->settings->secret('analytics', 'visitor_hash_key') !== null),
            $this->requirement('Visitor hash version', filled($resolved['analytics']['visitor_hash_version'] ?? null)),
            filled(config('services.linkpilot.analytics.geoip_database'))
                ? ['label' => 'GeoIP database', 'state' => 'ready', 'detail' => 'Deployment-owned GeoIP database is configured.']
                : ['label' => 'GeoIP database', 'state' => 'disabled', 'detail' => 'Country targeting stays unavailable until the deployment configures GeoIP.'],
        ];
    }

    /** @param array<string, mixed> $resolved
     * @return list<array{label: string, state: string, detail: string}>
     */
    private function uploading(array $resolved): array
    {
        $disk = $resolved['uploading']['disk'] ?? null;
        $configuration = is_string($disk) ? config('filesystems.disks.'.$disk) : null;
        $configured = is_array($configuration);
        $items = [$this->requirement('Storage disk', $configured)];

        if ($configured && ($configuration['driver'] ?? null) === 's3') {
            $items[] = $this->requirement('Access key ID', filled($configuration['key'] ?? null));
            $items[] = $this->requirement('Secret access key', filled($configuration['secret'] ?? null));
            $items[] = $this->requirement('Bucket', filled($configuration['bucket'] ?? null));
            $items[] = $this->requirement('Region', filled($configuration['region'] ?? null));
        }

        $items[] = filled(config('services.linkpilot.media.clamav_path'))
            ? ['label' => 'ClamAV executable', 'state' => 'ready', 'detail' => 'Deployment-owned scanner path is configured.']
            : ['label' => 'ClamAV executable', 'state' => 'incomplete', 'detail' => 'PDF uploads fail closed without a scanner.'];

        return $items;
    }

    /** @return list<array{label: string, state: string, detail: string}> */
    private function deployment(): array
    {
        $items = [
            $this->requirement('Application key', filled(config('app.key'))),
            $this->requirement('Database', $this->databaseAvailable()),
            ['label' => 'Cache driver', 'state' => 'ready', 'detail' => (string) config('cache.default', 'array')],
            ['label' => 'Session driver', 'state' => 'ready', 'detail' => (string) config('session.driver', 'file')],
            ['label' => 'Queue driver', 'state' => 'ready', 'detail' => (string) config('queue.default', 'sync')],
            $this->requirement('Canonical URL', filled(config('app.url'))),
            $this->requirement('Storage credentials', is_array(config('filesystems.disks.s3'))),
        ];

        return $items;
    }

    private function databaseAvailable(): bool
    {
        try {
            return Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{label: string, state: string, detail: string} */
    private function requirement(string $label, bool $ready): array
    {
        return [
            'label' => $label,
            'state' => $ready ? 'ready' : 'incomplete',
            'detail' => $ready ? 'Configured.' : 'Requires administrator input or deployment configuration.',
        ];
    }
}
