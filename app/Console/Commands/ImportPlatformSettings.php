<?php

namespace App\Console\Commands;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingSection;
use App\Services\Content\ContentMediaStorage;
use App\Services\Settings\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportPlatformSettings extends Command
{
    protected $signature = 'linkpilot:import-platform-settings {--dry-run : Report actions without writing settings}';

    protected $description = 'Import legacy environment-owned product settings into encrypted platform settings';

    public function handle(PlatformSettings $settings): int
    {
        $failed = false;
        $dryRun = (bool) $this->option('dry-run');
        [$candidates, $missing] = $this->candidates();

        foreach ($missing as $key) {
            $this->error('missing '.$key);
            $failed = true;
        }

        foreach ($candidates as $section => $values) {
            $pending = [];

            foreach ($values as $key => $value) {
                if ($settings->hasStored($section, $key)) {
                    $this->line('skipped '.$section.'.'.$key);
                } else {
                    $pending[$key] = $value;
                }
            }

            if ($pending === []) {
                continue;
            }

            if ($dryRun) {
                foreach (array_keys($pending) as $key) {
                    $this->line('would import '.$section.'.'.$key);
                }

                continue;
            }

            try {
                $result = $settings->importSection($section, $pending);
            } catch (ValidationException) {
                foreach (array_keys($pending) as $key) {
                    $this->error('invalid '.$section.'.'.$key);
                }
                $failed = true;

                continue;
            }

            foreach ($result['imported'] as $key) {
                $this->line('imported '.$section.'.'.$key);
            }
            foreach ($result['skipped'] as $key) {
                $this->line('skipped '.$section.'.'.$key);
            }
        }

        if ($this->convertLegacyAssets($dryRun)) {
            $failed = true;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function convertLegacyAssets(bool $dryRun): bool
    {
        $failed = false;
        $assets = [
            ['general', 'logo_light_id'],
            ['general', 'logo_dark_id'],
            ['general', 'compact_logo_id'],
            ['general', 'favicon_id'],
            ['biolinks', 'branding_media_id'],
            ['seo', 'og_media_id'],
        ];

        foreach ($assets as [$section, $key]) {
            $setting = PlatformSetting::query()
                ->where('section', $section)
                ->where('key', $key)
                ->where('is_secret', false)
                ->first();

            if (! $setting instanceof PlatformSetting) {
                continue;
            }

            $sourceId = json_decode((string) $setting->value, true);

            if (! is_int($sourceId) && ! (is_string($sourceId) && ctype_digit($sourceId))) {
                continue;
            }

            $source = ContentMedia::query()->find((int) $sourceId);

            if (! $source instanceof ContentMedia) {
                $this->error('failed '.$section.'.'.$key);
                $failed = true;

                continue;
            }

            if ($source->purpose === MediaPurpose::PlatformAsset && $source->workspace_id === null) {
                continue;
            }

            if ($dryRun) {
                $this->line('would convert '.$section.'.'.$key);

                continue;
            }

            try {
                $candidate = app(ContentMediaStorage::class)->copyToPlatformAsset($source, $source->uploader);
            } catch (Throwable) {
                $this->error('failed '.$section.'.'.$key);
                $failed = true;

                continue;
            }

            $converted = DB::transaction(function () use ($section, $key, $source, $candidate): bool {
                $state = PlatformSettingSection::query()
                    ->whereKey($section)
                    ->lockForUpdate()
                    ->first();
                $row = PlatformSetting::query()
                    ->where('section', $section)
                    ->where('key', $key)
                    ->lockForUpdate()
                    ->first();

                if (! $state instanceof PlatformSettingSection || ! $row instanceof PlatformSetting) {
                    return false;
                }

                if ((int) json_decode((string) $row->value, true) !== $source->getKey()) {
                    return false;
                }

                $row->forceFill([
                    'value' => json_encode($candidate->getKey(), JSON_THROW_ON_ERROR),
                    'is_secret' => false,
                    'updated_by' => null,
                ])->save();
                $state->increment('revision');

                return true;
            });

            if (! $converted) {
                app(ContentMediaStorage::class)->delete($candidate);
                $this->line('skipped '.$section.'.'.$key);

                continue;
            }

            $this->line('converted '.$section.'.'.$key);
            Cache::forget('platform-settings.resolved');
        }

        return $failed;
    }

    /**
     * @return array{array<string, array<string, mixed>>, list<string>}
     */
    private function candidates(): array
    {
        $values = [];
        $missing = [];
        $mailer = strtolower($this->environment('MAIL_MAILER') ?? '');
        $normalizedMailer = match ($mailer) {
            'smtp', 'smtps' => 'smtp',
            'array', 'log' => 'log',
            default => $mailer === '' ? null : 'log',
        };
        $host = $this->environment('MAIL_HOST');
        $port = $this->integer('MAIL_PORT');
        $scheme = strtolower($this->environment('MAIL_SCHEME') ?? '');

        $this->add($values, 'email', 'mailer', $normalizedMailer);
        $this->add($values, 'email', 'smtp_host', $host);
        $this->add($values, 'email', 'smtp_port', $port);
        if ($normalizedMailer === 'smtp') {
            $security = $mailer === 'smtps' || $scheme === 'smtps' || $port === 465
                ? 'tls'
                : 'starttls';
            $this->add($values, 'email', 'smtp_security', $security);
        }
        $username = $this->environment('MAIL_USERNAME');
        $password = $this->environment('MAIL_PASSWORD');
        $this->add($values, 'email', 'smtp_username', $username);
        $this->add($values, 'email', 'smtp_password', $password);
        $this->add($values, 'email', 'smtp_ehlo_domain', $this->environment('MAIL_EHLO_DOMAIN'));
        $this->add($values, 'email', 'smtp_timeout_seconds', $this->integer('MAIL_TIMEOUT'));
        $this->add($values, 'email', 'sender_address', $this->environment('MAIL_FROM_ADDRESS'));
        $this->add($values, 'email', 'sender_name', $this->environment('MAIL_FROM_NAME'));

        if ($normalizedMailer === 'smtp') {
            $this->require($missing, 'email.smtp_host', $host);
            $this->require($missing, 'email.smtp_port', $port);
            if (($username === null) !== ($password === null)) {
                $this->require($missing, 'email.smtp_username', $username);
                $this->require($missing, 'email.smtp_password', $password);
            }
        }

        $stripeKey = $this->environment('STRIPE_KEY');
        $stripeSecret = $this->environment('STRIPE_SECRET');
        $stripeWebhook = $this->environment('STRIPE_WEBHOOK_SECRET');
        $this->add($values, 'subscriptions', 'stripe_publishable_key', $stripeKey);
        $this->add($values, 'subscriptions', 'stripe_secret', $stripeSecret);
        $this->add($values, 'subscriptions', 'stripe_webhook_secret', $stripeWebhook);
        $this->add($values, 'subscriptions', 'currency', $this->environment('CASHIER_CURRENCY'));
        $this->add($values, 'subscriptions', 'tax_policy_acknowledged', $this->boolean('CASHIER_TAX_POLICY_ACKNOWLEDGED'));
        $this->add($values, 'subscriptions', 'promotion_codes', $this->boolean('BILLING_PROMOTION_CODES'));
        if ($stripeKey !== null || $stripeSecret !== null || $stripeWebhook !== null) {
            $this->require($missing, 'subscriptions.stripe_publishable_key', $stripeKey);
            $this->require($missing, 'subscriptions.stripe_secret', $stripeSecret);
            $this->require($missing, 'subscriptions.stripe_webhook_secret', $stripeWebhook);
        }

        $googleEnabled = $this->boolean('GOOGLE_AUTH_ENABLED');
        $googleId = $this->environment('GOOGLE_CLIENT_ID');
        $googleSecret = $this->environment('GOOGLE_CLIENT_SECRET');
        $this->add($values, 'authentication', 'google_enabled', $googleEnabled);
        $this->add($values, 'authentication', 'google_client_id', $googleId);
        $this->add($values, 'authentication', 'google_client_secret', $googleSecret);
        if ($googleEnabled === true) {
            $this->require($missing, 'authentication.google_client_id', $googleId);
            $this->require($missing, 'authentication.google_client_secret', $googleSecret);
        }

        $turnstileSite = $this->environment('TURNSTILE_SITE_KEY');
        $turnstileSecret = $this->environment('TURNSTILE_SECRET');
        if ($turnstileSite !== null || $turnstileSecret !== null) {
            $this->add($values, 'captcha', 'provider', 'turnstile');
            $this->add($values, 'captcha', 'on_registration', true);
            $this->require($missing, 'captcha.site_key', $turnstileSite);
            $this->require($missing, 'captcha.secret', $turnstileSecret);
        }
        $this->add($values, 'captcha', 'site_key', $turnstileSite);
        $this->add($values, 'captcha', 'secret', $turnstileSecret);

        $this->add($values, 'gdpr', 'privacy_url', $this->environment('PUBLIC_PRIVACY_URL'));
        $this->add($values, 'analytics', 'visitor_hash_key', $this->environment('ANALYTICS_HASH_KEY'));
        $this->add($values, 'analytics', 'visitor_hash_version', $this->environment('ANALYTICS_HASH_KEY_VERSION'));
        $this->add($values, 'analytics', 'retention_days', $this->integer('ANALYTICS_RETENTION_DAYS'));
        $this->add($values, 'uploading', 'disk', $this->environment('LINKPILOT_MEDIA_DISK'));
        $this->add($values, 'uploading', 's3_access_key_id', $this->environment('AWS_ACCESS_KEY_ID'));
        $this->add($values, 'uploading', 's3_secret_access_key', $this->environment('AWS_SECRET_ACCESS_KEY'));
        $this->add($values, 'uploading', 's3_region', $this->environment('AWS_DEFAULT_REGION'));
        $this->add($values, 'uploading', 's3_bucket', $this->environment('AWS_BUCKET'));
        $this->add($values, 'uploading', 's3_endpoint', $this->environment('AWS_ENDPOINT'));
        $this->add($values, 'uploading', 's3_use_path_style_endpoint', $this->boolean('AWS_USE_PATH_STYLE_ENDPOINT'));
        $this->add($values, 'uploading', 'retention_days', $this->integer('CONTENT_MEDIA_RETENTION_DAYS'));
        $this->add($values, 'system', 'api_rate_limit_per_minute', $this->integer('API_RATE_LIMIT_PER_MINUTE'));
        $this->add($values, 'system', 'webhook_retry_delays', $this->integerList('WEBHOOK_RETRY_DELAYS'));
        $this->add($values, 'system', 'webhook_connect_timeout_seconds', $this->integer('WEBHOOK_CONNECT_TIMEOUT'));
        $this->add($values, 'system', 'webhook_timeout_seconds', $this->integer('WEBHOOK_TIMEOUT'));
        $this->add($values, 'system', 'webhook_max_attempts', $this->integer('WEBHOOK_MAX_ATTEMPTS'));
        $this->add($values, 'system', 'webhook_excerpt_bytes', $this->integer('WEBHOOK_EXCERPT_BYTES'));

        return [$values, array_values(array_unique($missing))];
    }

    /** @param array<string, array<string, mixed>> $values */
    private function add(array &$values, string $section, string $key, mixed $value): void
    {
        if ($value !== null) {
            $values[$section][$key] = $value;
        }
    }

    /** @param list<string> $missing */
    private function require(array &$missing, string $key, mixed $value): void
    {
        if ($value === null) {
            $missing[] = $key;
        }
    }

    private function environment(string $key): ?string
    {
        $value = getenv($key);

        if ($value === false) {
            return null;
        }

        $value = trim($value);

        return $value === '' || strtolower($value) === 'null' ? null : $value;
    }

    private function boolean(string $key): ?bool
    {
        $value = $this->environment($key);

        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    private function integer(string $key): ?int
    {
        $value = $this->environment($key);

        return $value === null || filter_var($value, FILTER_VALIDATE_INT) === false ? null : (int) $value;
    }

    /** @return list<int>|null */
    private function integerList(string $key): ?array
    {
        $value = $this->environment($key);

        if ($value === null) {
            return null;
        }

        return array_map('intval', array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item): bool => $item !== '')));
    }
}
