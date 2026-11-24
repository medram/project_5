<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\PlatformSetting;
use App\Models\PlatformSettingSection;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSettingsImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const LEGACY_KEYS = [
        'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME',
        'MAIL_PASSWORD', 'MAIL_EHLO_DOMAIN', 'MAIL_TIMEOUT', 'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME', 'STRIPE_KEY', 'STRIPE_SECRET', 'STRIPE_WEBHOOK_SECRET',
        'CASHIER_CURRENCY', 'CASHIER_TAX_POLICY_ACKNOWLEDGED', 'BILLING_PROMOTION_CODES',
        'GOOGLE_AUTH_ENABLED', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET',
        'TURNSTILE_SITE_KEY', 'TURNSTILE_SECRET', 'PUBLIC_PRIVACY_URL',
        'ANALYTICS_HASH_KEY', 'ANALYTICS_HASH_KEY_VERSION', 'ANALYTICS_RETENTION_DAYS',
        'LINKPILOT_MEDIA_DISK', 'CONTENT_MEDIA_RETENTION_DAYS',
        'API_RATE_LIMIT_PER_MINUTE', 'WEBHOOK_RETRY_DELAYS', 'WEBHOOK_CONNECT_TIMEOUT',
        'WEBHOOK_TIMEOUT', 'WEBHOOK_MAX_ATTEMPTS', 'WEBHOOK_EXCERPT_BYTES',
    ];

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::LEGACY_KEYS as $key) {
            $this->originalEnvironment[$key] = getenv($key);
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv($key.'='.$value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        parent::tearDown();
    }

    public function test_import_is_dry_run_idempotent_redacted_and_never_overwrites_existing_rows(): void
    {
        PlatformSetting::query()->create([
            'section' => 'email',
            'key' => 'sender_name',
            'value' => '"Administrator"',
            'is_secret' => false,
        ]);
        PlatformSettingSection::query()->whereKey('email')->update(['revision' => 4]);
        $this->setCompleteLegacyEnvironment();

        $this->assertSame(0, Artisan::call('linkpilot:import-platform-settings', ['--dry-run' => true]));
        $dryRunOutput = Artisan::output();
        $this->assertStringContainsString('email.smtp_host', $dryRunOutput);
        $this->assertStringContainsString('subscriptions.stripe_secret', $dryRunOutput);
        $this->assertStringNotContainsString('smtp.legacy.test', $dryRunOutput);
        $this->assertStringNotContainsString('sk_test_import_secret', $dryRunOutput);
        $this->assertDatabaseCount('platform_settings', 1);
        $this->assertSame(4, app(PlatformSettings::class)->version('email'));

        $this->assertSame(0, Artisan::call('linkpilot:import-platform-settings'));
        $output = Artisan::output();
        $this->assertStringContainsString('skipped email.sender_name', $output);
        $this->assertStringNotContainsString('Legacy Sender', $output);
        $this->assertStringNotContainsString('google-import-secret', $output);

        $this->assertDatabaseHas('platform_settings', [
            'section' => 'email',
            'key' => 'sender_name',
            'value' => '"Administrator"',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'section' => 'email',
            'key' => 'smtp_security',
            'value' => '"starttls"',
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'section' => 'system',
            'key' => 'webhook_retry_delays',
            'value' => '[30,120]',
        ]);

        $settings = app(PlatformSettings::class);
        $this->assertSame('smtp-import-password', $settings->secret('email', 'smtp_password'));
        $this->assertSame('sk_test_import_secret', $settings->secret('subscriptions', 'stripe_secret'));
        $this->assertSame('google-import-secret', $settings->secret('authentication', 'google_client_secret'));
        $this->assertSame('turnstile-import-secret', $settings->secret('captcha', 'secret'));
        $this->assertArrayNotHasKey('smtp_password', $settings->resolved()['email']);

        $secretRow = PlatformSetting::query()
            ->where('section', 'subscriptions')
            ->where('key', 'stripe_secret')
            ->sole();
        $this->assertStringNotContainsString('sk_test_import_secret', (string) $secretRow->value);
        $this->assertSame('sk_test_import_secret', json_decode(Crypt::decryptString((string) $secretRow->value), true));

        $this->assertSame(5, $settings->version('email'));
        foreach (['subscriptions', 'authentication', 'captcha', 'gdpr', 'analytics', 'uploading', 'system'] as $section) {
            $this->assertSame(1, $settings->version($section));
        }

        $rowCount = PlatformSetting::query()->count();
        $revisions = PlatformSettingSection::query()->pluck('revision', 'section')->all();
        $this->assertSame(0, Artisan::call('linkpilot:import-platform-settings'));
        $this->assertSame($rowCount, PlatformSetting::query()->count());
        $this->assertSame($revisions, PlatformSettingSection::query()->pluck('revision', 'section')->all());
    }

    public function test_import_exits_nonzero_for_an_incomplete_enabled_provider_without_printing_values(): void
    {
        $this->legacy('GOOGLE_AUTH_ENABLED', 'true');
        $this->legacy('GOOGLE_CLIENT_ID', 'client-id.apps.googleusercontent.com');

        $this->assertSame(1, Artisan::call('linkpilot:import-platform-settings'));
        $output = Artisan::output();

        $this->assertStringContainsString('authentication.google_client_secret', $output);
        $this->assertStringNotContainsString('client-id.apps.googleusercontent.com', $output);
    }

    public function test_import_converts_referenced_tenant_media_to_a_platform_asset_once(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        Storage::fake('import-media');
        config()->set('filesystems.disks.import-media', ['driver' => 'local']);
        config()->set('services.linkpilot.media.disk', 'import-media');
        Storage::disk('import-media')->put('workspaces/'.$workspace->id.'/tenant.png', $this->pngBytes());
        $source = ContentMedia::query()->create([
            'workspace_id' => $workspace->id,
            'uploaded_by' => $administrator->id,
            'public_id' => (string) Str::uuid(),
            'disk' => 'import-media',
            'path' => 'workspaces/'.$workspace->id.'/tenant.png',
            'original_name' => 'tenant.png',
            'mime_type' => 'image/png',
            'byte_size' => Storage::disk('import-media')->size('workspaces/'.$workspace->id.'/tenant.png'),
            'sha256' => hash('sha256', Storage::disk('import-media')->get('workspaces/'.$workspace->id.'/tenant.png')),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'purpose' => MediaPurpose::Image,
            'expires_at' => now()->addDays(30),
        ]);
        PlatformSetting::query()->create([
            'section' => 'general',
            'key' => 'logo_light_id',
            'value' => json_encode($source->id),
            'is_secret' => false,
        ]);
        PlatformSettingSection::query()->whereKey('general')->update(['revision' => 3]);

        $this->assertSame(0, Artisan::call('linkpilot:import-platform-settings', ['--dry-run' => true]));
        $this->assertStringContainsString('would convert general.logo_light_id', Artisan::output());
        $this->assertSame(1, ContentMedia::query()->count());

        $this->assertSame(0, Artisan::call('linkpilot:import-platform-settings'));
        $this->assertStringContainsString('converted general.logo_light_id', Artisan::output());

        $replacement = ContentMedia::query()->where('purpose', MediaPurpose::PlatformAsset)->sole();
        $this->assertNull($replacement->workspace_id);
        Storage::disk('import-media')->assertExists($replacement->path);
        $this->assertDatabaseHas('platform_settings', [
            'section' => 'general',
            'key' => 'logo_light_id',
            'value' => json_encode($replacement->id),
        ]);
        $this->assertSame($source->id, $source->refresh()->id);
        $this->assertSame(4, app(PlatformSettings::class)->version('general'));

        $count = ContentMedia::query()->count();
        $revisions = PlatformSettingSection::query()->pluck('revision', 'section')->all();
        $this->assertSame(0, Artisan::call('linkpilot:import-platform-settings'));
        $this->assertSame($count, ContentMedia::query()->count());
        $this->assertSame($revisions, PlatformSettingSection::query()->pluck('revision', 'section')->all());
    }

    public function test_unreadable_legacy_media_blocks_cutover_without_changing_the_setting(): void
    {
        $administrator = User::factory()->admin()->create();
        $workspace = $administrator->personalWorkspace()->firstOrFail();
        $source = ContentMedia::query()->create([
            'workspace_id' => $workspace->id,
            'uploaded_by' => $administrator->id,
            'public_id' => (string) Str::uuid(),
            'disk' => 'missing-import-disk',
            'path' => 'workspaces/'.$workspace->id.'/missing.png',
            'original_name' => 'missing.png',
            'mime_type' => 'image/png',
            'byte_size' => 10,
            'sha256' => str_repeat('c', 64),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'purpose' => MediaPurpose::Image,
            'expires_at' => now()->addDays(30),
        ]);
        config()->set('filesystems.disks.missing-import-disk', ['driver' => 'local', 'root' => storage_path('framework/testing/missing-import')]);
        PlatformSetting::query()->create([
            'section' => 'general',
            'key' => 'logo_light_id',
            'value' => json_encode($source->id),
            'is_secret' => false,
        ]);

        $this->assertSame(1, Artisan::call('linkpilot:import-platform-settings'));
        $this->assertStringContainsString('failed general.logo_light_id', Artisan::output());
        $this->assertDatabaseHas('platform_settings', [
            'section' => 'general',
            'key' => 'logo_light_id',
            'value' => json_encode($source->id),
        ]);
        $this->assertDatabaseCount('content_media', 1);
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) ? $bytes : '';
    }

    private function setCompleteLegacyEnvironment(): void
    {
        $values = [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => 'smtp.legacy.test',
            'MAIL_PORT' => '587',
            'MAIL_SCHEME' => 'null',
            'MAIL_USERNAME' => 'smtp-import-user',
            'MAIL_PASSWORD' => 'smtp-import-password',
            'MAIL_EHLO_DOMAIN' => 'linkpilot.test',
            'MAIL_TIMEOUT' => '15',
            'MAIL_FROM_ADDRESS' => 'mail@example.test',
            'MAIL_FROM_NAME' => 'Legacy Sender',
            'STRIPE_KEY' => 'pk_test_import_public',
            'STRIPE_SECRET' => 'sk_test_import_secret',
            'STRIPE_WEBHOOK_SECRET' => 'whsec_import_secret',
            'CASHIER_CURRENCY' => 'usd',
            'CASHIER_TAX_POLICY_ACKNOWLEDGED' => 'true',
            'BILLING_PROMOTION_CODES' => 'true',
            'GOOGLE_AUTH_ENABLED' => 'true',
            'GOOGLE_CLIENT_ID' => 'client-id.apps.googleusercontent.com',
            'GOOGLE_CLIENT_SECRET' => 'google-import-secret',
            'TURNSTILE_SITE_KEY' => 'turnstile-import-site',
            'TURNSTILE_SECRET' => 'turnstile-import-secret',
            'PUBLIC_PRIVACY_URL' => 'https://example.test/privacy',
            'ANALYTICS_HASH_KEY' => '0123456789abcdef0123456789abcdef',
            'ANALYTICS_HASH_KEY_VERSION' => 'v2',
            'ANALYTICS_RETENTION_DAYS' => '45',
            'LINKPILOT_MEDIA_DISK' => 'local',
            'CONTENT_MEDIA_RETENTION_DAYS' => '120',
            'API_RATE_LIMIT_PER_MINUTE' => '90',
            'WEBHOOK_RETRY_DELAYS' => '30,120',
            'WEBHOOK_CONNECT_TIMEOUT' => '4',
            'WEBHOOK_TIMEOUT' => '12',
            'WEBHOOK_MAX_ATTEMPTS' => '4',
            'WEBHOOK_EXCERPT_BYTES' => '750',
        ];

        foreach ($values as $key => $value) {
            $this->legacy($key, $value);
        }
    }

    private function legacy(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
