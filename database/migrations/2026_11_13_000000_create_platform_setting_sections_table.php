<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyRevisions = Schema::hasColumn('platform_settings', 'version')
            ? DB::table('platform_settings')
                ->select('section', DB::raw('MAX(version) as revision'))
                ->groupBy('section')
                ->pluck('revision', 'section')
            : collect();

        Schema::create('platform_setting_sections', function (Blueprint $table): void {
            $table->string('section', 60)->primary();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = collect(array_keys(config('platform-settings.sections', [])))
            ->map(fn (string $section): array => [
                'section' => $section,
                'revision' => (int) ($legacyRevisions[$section] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('platform_setting_sections')->insert($rows);
        }

        if (Schema::hasColumn('platform_settings', 'version')) {
            Schema::table('platform_settings', function (Blueprint $table): void {
                $table->dropColumn('version');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('platform_settings', 'version')) {
            Schema::table('platform_settings', function (Blueprint $table): void {
                $table->unsignedBigInteger('version')->default(1);
            });
        }

        if (Schema::hasTable('platform_setting_sections')) {
            DB::table('platform_setting_sections')
                ->orderBy('section')
                ->each(function (object $section): void {
                    DB::table('platform_settings')
                        ->where('section', $section->section)
                        ->update(['version' => (int) $section->revision]);
                });
        }

        Schema::dropIfExists('platform_setting_sections');
    }
};
