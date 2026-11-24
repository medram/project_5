<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PlatformSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_migration_rolls_back_row_versions_without_deleting_operator_values(): void
    {
        $administrator = User::factory()->admin()->create();
        $settings = app(PlatformSettings::class);
        $settings->updateSection($administrator, 'general', ['site_name' => 'Acme'], 0);
        $settings->updateSection($administrator, 'general', ['support_email' => 'support@acme.test'], 1);

        $migration = require database_path('migrations/2026_11_13_000000_create_platform_setting_sections_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('platform_setting_sections'));
        $this->assertTrue(Schema::hasColumn('platform_settings', 'version'));
        $this->assertSame(
            [
                ['key' => 'site_name', 'value' => '"Acme"', 'version' => 2],
                ['key' => 'support_email', 'value' => '"support@acme.test"', 'version' => 2],
            ],
            DB::table('platform_settings')
                ->where('section', 'general')
                ->orderBy('key')
                ->get(['key', 'value', 'version'])
                ->map(fn (object $row): array => (array) $row)
                ->all(),
        );

        $migration->up();

        $this->assertTrue(Schema::hasTable('platform_setting_sections'));
        $this->assertFalse(Schema::hasColumn('platform_settings', 'version'));
        $this->assertDatabaseHas('platform_setting_sections', [
            'section' => 'general',
            'revision' => 2,
        ]);
        $this->assertDatabaseHas('platform_settings', [
            'section' => 'general',
            'key' => 'site_name',
            'value' => '"Acme"',
        ]);
    }

    public function test_normalization_is_idempotent_and_increments_each_affected_section_once(): void
    {
        $now = now();
        $legacy = [
            ['section' => 'biolinks', 'key' => 'available_blocks', 'value' => json_encode(['links', 'image', 'divider'], JSON_THROW_ON_ERROR)],
            ['section' => 'subscriptions', 'key' => 'invoice_address', 'value' => json_encode('required', JSON_THROW_ON_ERROR)],
            ['section' => 'subscriptions', 'key' => 'invoice_notes', 'value' => json_encode('Legacy note', JSON_THROW_ON_ERROR)],
            ['section' => 'localization', 'key' => 'date_format', 'value' => json_encode('Y-m-d', JSON_THROW_ON_ERROR)],
            ['section' => 'authentication', 'key' => 'email_verification_required', 'value' => json_encode(false, JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'contact_recipient', 'value' => json_encode('legacy@example.test', JSON_THROW_ON_ERROR)],
            ['section' => 'captcha', 'key' => 'on_public_actions', 'value' => json_encode(true, JSON_THROW_ON_ERROR)],
            ['section' => 'gdpr', 'key' => 'analytics_default_denied', 'value' => json_encode(false, JSON_THROW_ON_ERROR)],
        ];

        DB::table('platform_settings')->insert(array_map(
            fn (array $row): array => [
                ...$row,
                'is_secret' => false,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $legacy,
        ));
        $affectedSections = ['biolinks', 'subscriptions', 'localization', 'authentication', 'email', 'captcha', 'gdpr'];
        DB::table('platform_setting_sections')->whereIn('section', $affectedSections)->update(['revision' => 7]);

        $migration = require database_path('migrations/2026_11_13_020000_normalize_platform_settings.php');
        $migration->up();

        $this->assertSame(
            ['link', 'text', 'image', 'social'],
            json_decode((string) DB::table('platform_settings')
                ->where('section', 'biolinks')
                ->where('key', 'available_blocks')
                ->value('value'), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertSame(
            'required',
            json_decode((string) DB::table('platform_settings')
                ->where('section', 'subscriptions')
                ->where('key', 'billing_address_collection')
                ->value('value'), true, 512, JSON_THROW_ON_ERROR),
        );
        $this->assertSame(
            0,
            DB::table('platform_settings')->whereIn('key', [
                'available_widgets',
                'invoice_address',
                'invoice_notes',
                'date_format',
                'email_verification_required',
                'contact_recipient',
                'on_public_actions',
                'analytics_default_denied',
            ])->count(),
        );

        foreach ($affectedSections as $section) {
            $this->assertDatabaseHas('platform_setting_sections', [
                'section' => $section,
                'revision' => 8,
            ]);
        }

        $migration->up();
        $migration->down();

        foreach ($affectedSections as $section) {
            $this->assertDatabaseHas('platform_setting_sections', [
                'section' => $section,
                'revision' => 8,
            ]);
        }
        $this->assertDatabaseHas('platform_settings', [
            'section' => 'subscriptions',
            'key' => 'billing_address_collection',
            'value' => '"required"',
        ]);
    }

    public function test_nullable_workspace_rollback_refuses_to_delete_platform_assets(): void
    {
        $user = User::factory()->create();
        DB::table('content_media')->insert([
            'workspace_id' => null,
            'uploaded_by' => $user->id,
            'public_id' => (string) Str::uuid(),
            'disk' => 'local',
            'path' => 'platform-assets/example.webp',
            'original_name' => 'example.webp',
            'mime_type' => 'image/webp',
            'byte_size' => 10,
            'sha256' => str_repeat('a', 64),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'purpose' => 'platform_asset',
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_11_13_010000_make_content_media_workspace_nullable.php');

        try {
            $migration->down();
            $this->fail('Rollback must refuse to orphan or delete platform assets.');
        } catch (LogicException $exception) {
            $this->assertSame(
                'Cannot require a workspace while platform assets exist.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('content_media', [
            'workspace_id' => null,
            'purpose' => 'platform_asset',
        ]);

        DB::table('content_media')->delete();
        $migration->down();
        $this->assertFalse($this->workspaceIdIsNullable());

        $migration->up();
        $this->assertTrue($this->workspaceIdIsNullable());
    }

    public function test_mailpit_email_transport_is_removed_once_without_touching_real_smtp_settings(): void
    {
        $now = now();
        $insert = function (array $rows) use ($now): void {
            DB::table('platform_settings')->insert(array_map(
                fn (array $row): array => [
                    ...$row,
                    'is_secret' => false,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $rows,
            ));
        };

        $insert([
            ['section' => 'email', 'key' => 'mailer', 'value' => json_encode('smtp', JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'smtp_host', 'value' => json_encode('mailpit', JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'smtp_port', 'value' => json_encode(1025, JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'smtp_security', 'value' => json_encode('starttls', JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'sender_name', 'value' => json_encode('Administrator', JSON_THROW_ON_ERROR)],
        ]);
        DB::table('platform_setting_sections')->where('section', 'email')->update(['revision' => 3]);

        $migration = require database_path('migrations/2026_11_24_000000_remove_mailpit_email_transport.php');
        $migration->up();

        $this->assertSame(0, DB::table('platform_settings')
            ->where('section', 'email')
            ->whereIn('key', ['mailer', 'smtp_host', 'smtp_port', 'smtp_security'])
            ->count());
        $this->assertDatabaseHas('platform_settings', [
            'section' => 'email',
            'key' => 'sender_name',
            'value' => '"Administrator"',
        ]);
        $this->assertDatabaseHas('platform_setting_sections', [
            'section' => 'email',
            'revision' => 4,
        ]);
        $this->assertSame('log', app(PlatformSettings::class)->resolved()['email']['mailer']);

        $migration->up();
        $this->assertDatabaseHas('platform_setting_sections', [
            'section' => 'email',
            'revision' => 4,
        ]);

        $insert([
            ['section' => 'email', 'key' => 'mailer', 'value' => json_encode('smtp', JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'smtp_host', 'value' => json_encode('smtp.acme.test', JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'smtp_port', 'value' => json_encode(587, JSON_THROW_ON_ERROR)],
            ['section' => 'email', 'key' => 'smtp_security', 'value' => json_encode('starttls', JSON_THROW_ON_ERROR)],
        ]);

        $migration->up();

        $this->assertDatabaseHas('platform_settings', [
            'section' => 'email',
            'key' => 'smtp_host',
            'value' => '"smtp.acme.test"',
        ]);
        $this->assertDatabaseHas('platform_setting_sections', [
            'section' => 'email',
            'revision' => 4,
        ]);
    }

    private function workspaceIdIsNullable(): bool
    {
        $column = collect(Schema::getColumns('content_media'))->firstWhere('name', 'workspace_id');

        return (bool) ($column['nullable'] ?? false);
    }
}
