<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings') || ! Schema::hasTable('platform_setting_sections')) {
            return;
        }

        DB::transaction(function (): void {
            $state = DB::table('platform_setting_sections')
                ->where('section', 'email')
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                return;
            }

            $host = DB::table('platform_settings')
                ->where('section', 'email')
                ->where('key', 'smtp_host')
                ->lockForUpdate()
                ->first();

            if ($host === null || json_decode((string) $host->value, true) !== 'mailpit') {
                return;
            }

            DB::table('platform_settings')
                ->where('section', 'email')
                ->whereIn('key', ['mailer', 'smtp_host', 'smtp_port', 'smtp_security'])
                ->delete();

            DB::table('platform_setting_sections')
                ->where('section', 'email')
                ->update([
                    'revision' => (int) $state->revision + 1,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // Intentionally data-preserving: the removed development mail catcher cannot be reconstructed truthfully.
    }
};
