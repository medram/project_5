<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\ApplyPlatformSettings;
use App\Models\OperationLog;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class PlatformSettingsRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_runtime_configuration_is_applied_from_database_settings(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        $settings->updateSection($administrator, 'general', [
            'site_name' => 'Database Name',
        ], $settings->version('general'));
        $settings->updateSection($administrator, 'email', [
            'mailer' => 'smtp',
            'smtp_host' => 'smtp.acme.test',
            'smtp_port' => 2525,
            'smtp_security' => 'starttls',
            'smtp_username' => 'smtp-user',
            'smtp_password' => 'smtp-password',
            'smtp_timeout_seconds' => 12,
            'smtp_ehlo_domain' => 'mail.acme.test',
            'sender_name' => 'Acme Mail',
            'sender_address' => 'mail@acme.test',
        ], $settings->version('email'));
        $settings->updateSection($administrator, 'subscriptions', [
            'billing_enabled' => true,
            'annual_display' => false,
            'promotion_codes' => true,
            'billing_address_collection' => 'required',
            'stripe_publishable_key' => 'pk_test_acme',
            'stripe_secret' => 'sk_test_acme',
            'stripe_webhook_secret' => 'whsec_acme',
            'currency' => 'eur',
            'tax_policy_acknowledged' => true,
        ], $settings->version('subscriptions'));
        $settings->updateSection($administrator, 'authentication', [
            'google_enabled' => true,
            'google_client_id' => 'acme.apps.googleusercontent.com',
            'google_client_secret' => 'google-acme-secret',
        ], $settings->version('authentication'));
        $settings->updateSection($administrator, 'analytics', [
            'consent_enabled' => true,
            'retention_days' => 45,
            'visitor_hash_key' => '0123456789abcdef0123456789abcdef',
            'visitor_hash_version' => 'v3',
        ], $settings->version('analytics'));
        $settings->updateSection($administrator, 'uploading', [
            'disk' => 'local',
            'retention_days' => 120,
        ], $settings->version('uploading'));
        $settings->updateSection($administrator, 'system', [
            'api_rate_limit_per_minute' => 77,
            'webhook_retry_delays' => [30, 90],
            'webhook_connect_timeout_seconds' => 5,
            'webhook_timeout_seconds' => 20,
            'webhook_max_attempts' => 4,
            'webhook_excerpt_bytes' => 750,
        ], $settings->version('system'));

        config([
            'mail.default' => 'array',
            'cashier.key' => 'env_key',
            'cashier.secret' => 'env_secret',
            'linkpilot.api.rate_limit_per_minute' => 1,
        ]);

        $settings->applyRuntime();

        $this->assertSame('Database Name', config('app.name'));
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.acme.test', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertTrue(config('mail.mailers.smtp.auto_tls'));
        $this->assertSame('smtp-user', config('mail.mailers.smtp.username'));
        $this->assertSame('smtp-password', config('mail.mailers.smtp.password'));
        $this->assertSame(12, config('mail.mailers.smtp.timeout'));
        $this->assertSame('mail.acme.test', config('mail.mailers.smtp.local_domain'));
        $this->assertSame('Acme Mail', config('mail.from.name'));
        $this->assertSame('mail@acme.test', config('mail.from.address'));

        $this->assertSame('pk_test_acme', config('cashier.key'));
        $this->assertSame('sk_test_acme', config('cashier.secret'));
        $this->assertSame('whsec_acme', config('cashier.webhook.secret'));
        $this->assertSame('eur', config('cashier.currency'));
        $this->assertTrue(config('cashier.tax_policy_acknowledged'));
        $this->assertTrue(config('linkpilot.billing.promotion_codes'));
        $this->assertSame('acme.apps.googleusercontent.com', config('services.google.client_id'));
        $this->assertSame('google-acme-secret', config('services.google.client_secret'));
        $this->assertSame('0123456789abcdef0123456789abcdef', config('services.linkpilot.analytics.hash_key'));
        $this->assertSame('v3', config('services.linkpilot.analytics.hash_key_version'));
        $this->assertSame(45, config('services.linkpilot.analytics.retention_days'));
        $this->assertSame('local', config('services.linkpilot.media.disk'));
        $this->assertSame(120, config('services.linkpilot.media.retention_days'));
        $this->assertSame(77, config('linkpilot.api.rate_limit_per_minute'));
        $this->assertSame([30, 90], config('services.linkpilot.webhooks.retry_delays'));
        $this->assertSame(5, config('services.linkpilot.webhooks.connect_timeout'));
        $this->assertSame(20, config('services.linkpilot.webhooks.timeout'));
        $this->assertSame(4, config('services.linkpilot.webhooks.max_attempts'));
        $this->assertSame(750, config('services.linkpilot.webhooks.excerpt_bytes'));
    }

    public function test_revision_changes_at_the_same_timestamp_refresh_cached_integrations(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-11-13 12:00:00');

        try {
            $administrator = User::factory()->admin()->create();
            $settings = app(PlatformSettings::class);
            $settings->updateSection($administrator, 'email', [
                'mailer' => 'smtp',
                'smtp_host' => 'first.smtp.test',
                'smtp_port' => 587,
                'smtp_security' => 'starttls',
            ], $settings->version('email'));
            $settings->updateSection($administrator, 'authentication', [
                'google_enabled' => true,
                'google_client_id' => 'first.apps.googleusercontent.com',
                'google_client_secret' => 'first-secret',
            ], $settings->version('authentication'));
            $settings->applyRuntime();

            $mail = app(\Illuminate\Mail\MailManager::class);
            $socialite = app(\Laravel\Socialite\Contracts\Factory::class);
            $firstMailer = $mail->mailer('smtp');
            $firstGoogleDriver = $socialite->driver('google');

            $settings->updateSection($administrator, 'email', [
                'smtp_host' => 'second.smtp.test',
            ], $settings->version('email'));
            $settings->updateSection($administrator, 'authentication', [
                'google_client_id' => 'second.apps.googleusercontent.com',
            ], $settings->version('authentication'));
            $settings->applyRuntime();

            $this->assertNotSame($firstMailer, $mail->mailer('smtp'));
            $this->assertNotSame($firstGoogleDriver, $socialite->driver('google'));
            $this->assertSame('second.smtp.test', config('mail.mailers.smtp.host'));
            $this->assertSame('second.apps.googleusercontent.com', config('services.google.client_id'));
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_smtp_security_maps_to_supported_transport_modes(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);

        foreach ([
            'tls' => ['smtps', false],
            'none' => ['smtp', false],
            'starttls' => ['smtp', true],
        ] as $security => [$scheme, $autoTls]) {
            $settings->updateSection($administrator, 'email', [
                'mailer' => 'smtp',
                'smtp_host' => 'smtp.acme.test',
                'smtp_port' => 587,
                'smtp_security' => $security,
            ], $settings->version('email'));
            $settings->applyRuntime();

            $this->assertSame($scheme, config('mail.mailers.smtp.scheme'), $security);
            $this->assertSame($autoTls, config('mail.mailers.smtp.auto_tls'), $security);
            $this->assertTrue(config('mail.mailers.smtp.verify_peer'), $security);
        }
    }

    public function test_legacy_tax_policy_environment_does_not_configure_cashier(): void
    {
        $this->assertFalse(config('cashier.tax_policy_acknowledged'));
    }

    public function test_apply_runtime_is_a_noop_when_settings_tables_are_absent(): void
    {
        config(['mail.default' => 'array']);

        $migration = require database_path('migrations/2026_11_13_000000_create_platform_setting_sections_table.php');
        $migration->down();

        app(PlatformSettings::class)->applyRuntime();

        $this->assertSame('array', config('mail.default'));
    }

    public function test_web_and_api_requests_apply_database_settings(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'general', [
            'site_name' => 'Request Name',
        ], $settings->version('general'));

        $this->get('/');
        $this->assertSame('Request Name', config('app.name'));

        config(['app.name' => 'Before Api']);
        $settings->updateSection($administrator, 'general', [
            'site_name' => 'Api Name',
        ], $settings->version('general'));
        $this->getJson('/api/v1/links')->assertUnauthorized();
        $this->assertSame('Api Name', config('app.name'));
    }

    public function test_console_command_starting_applies_settings_before_work(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'general', [
            'site_name' => 'Console Name',
        ], $settings->version('general'));

        config(['app.name' => 'Before Command']);
        Event::dispatch(new CommandStarting('inspire', new ArrayInput([]), new NullOutput));

        $this->assertSame('Console Name', config('app.name'));
    }

    public function test_operation_log_pruning_uses_the_documented_system_retention_setting(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'system', [
            'log_retention_days' => 365,
        ], $settings->version('system'));

        OperationLog::query()->create(['level' => 'info', 'channel' => 'app', 'event' => 'retained', 'message' => 'retained']);
        OperationLog::query()->where('event', 'retained')->update(['created_at' => now()->subDays(200)]);
        OperationLog::query()->create(['level' => 'info', 'channel' => 'app', 'event' => 'recent', 'message' => 'recent']);

        $this->artisan('linkpilot:prune-operation-logs')->assertSuccessful();

        $this->assertDatabaseHas('operation_logs', ['event' => 'retained']);
        $this->assertDatabaseHas('operation_logs', ['event' => 'recent']);
    }

    public function test_queue_job_processing_applies_settings_before_work(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'general', [
            'site_name' => 'Queue Name',
        ], $settings->version('general'));

        config(['app.name' => 'Before Queue']);
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn([]);
        Event::dispatch(new JobProcessing('queue', $job));

        $this->assertSame('Queue Name', config('app.name'));
    }


    public function test_one_shot_queue_worker_applies_settings_before_handling_a_job(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'general', [
            'site_name' => 'One-shot Queue Name',
        ], $settings->version('general'));

        config([
            'app.name' => 'Stale Queue Name',
            'queue.default' => 'database',
            'queue.connections.database.queue' => 'default',
        ]);
        RuntimeSettingsObservationJob::$observedAppName = null;
        RuntimeSettingsObservationJob::dispatch();

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--sleep' => 0,
            '--tries' => 1,
        ])->assertExitCode(0);

        $this->assertSame('One-shot Queue Name', RuntimeSettingsObservationJob::$observedAppName);
    }
    public function test_cashier_payment_and_webhook_routes_are_owned_by_the_application(): void
    {
        $this->assertTrue(Route::has('cashier.payment'));
        $this->assertTrue(Route::has('cashier.webhook'));
        $this->assertSame('stripe/payment/{id}', Route::getRoutes()->getByName('cashier.payment')->uri());
        $this->assertSame('stripe/webhook', Route::getRoutes()->getByName('cashier.webhook')->uri());
    }

    public function test_apply_platform_settings_runs_in_both_request_groups(): void
    {
        $groups = app('router')->getMiddlewareGroups();

        $this->assertLessThan(
            array_search(ApplyPlatformSettings::class, $groups['web'], true),
            array_search(\App\Http\Middleware\EnsureAllowedHost::class, $groups['web'], true),
        );
        $this->assertLessThan(
            array_search(\Illuminate\Routing\Middleware\SubstituteBindings::class, $groups['web'], true),
            array_search(ApplyPlatformSettings::class, $groups['web'], true),
        );
        $this->assertLessThan(
            array_search(\Illuminate\Routing\Middleware\SubstituteBindings::class, $groups['api'], true),
            array_search(ApplyPlatformSettings::class, $groups['api'], true),
        );

        $this->assertContains(ApplyPlatformSettings::class, $groups['web']);
        $this->assertContains(ApplyPlatformSettings::class, $groups['api']);
    }
}

final class RuntimeSettingsObservationJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable;
    use \Illuminate\Foundation\Bus\Dispatchable;
    use \Illuminate\Queue\InteractsWithQueue;
    use \Illuminate\Queue\SerializesModels;

    public static ?string $observedAppName = null;

    public function handle(): void
    {
        self::$observedAppName = config('app.name');
    }
}
