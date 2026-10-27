<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_pixel_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_pixel_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('trigger', 32);
            $table->timestamps();

            $table->unique(
                ['tracking_pixel_id', 'target_type', 'target_id', 'trigger'],
                'tracking_pixel_rules_unique',
            );
            $table->index(['workspace_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id', 'trigger']);
        });

        $this->migratePageAttachments('link_page_tracking_pixel', 'link_page', 'link_pages', 'link_page_id');
        $this->migratePageAttachments('link_overlay_tracking_pixel', 'link_overlay', 'link_overlays', 'link_overlay_id');

        Schema::dropIfExists('link_page_tracking_pixel');
        Schema::dropIfExists('link_overlay_tracking_pixel');
    }

    public function down(): void
    {
        if (! Schema::hasTable('link_page_tracking_pixel')) {
            Schema::create('link_page_tracking_pixel', function (Blueprint $table): void {
                $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tracking_pixel_id')->constrained()->cascadeOnDelete();
                $table->unique(['link_page_id', 'tracking_pixel_id']);
            });
        }

        if (! Schema::hasTable('link_overlay_tracking_pixel')) {
            Schema::create('link_overlay_tracking_pixel', function (Blueprint $table): void {
                $table->foreignId('link_overlay_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tracking_pixel_id')->constrained()->cascadeOnDelete();
                $table->unique(['link_overlay_id', 'tracking_pixel_id']);
            });
        }

        if (Schema::hasTable('tracking_pixel_rules')) {
            foreach (DB::table('tracking_pixel_rules')->where('target_type', 'link_page')->where('trigger', 'page_view')->get(['target_id', 'tracking_pixel_id']) as $rule) {
                DB::table('link_page_tracking_pixel')->insertOrIgnore([
                    'link_page_id' => $rule->target_id,
                    'tracking_pixel_id' => $rule->tracking_pixel_id,
                ]);
            }

            foreach (DB::table('tracking_pixel_rules')->where('target_type', 'link_overlay')->where('trigger', 'page_view')->get(['target_id', 'tracking_pixel_id']) as $rule) {
                DB::table('link_overlay_tracking_pixel')->insertOrIgnore([
                    'link_overlay_id' => $rule->target_id,
                    'tracking_pixel_id' => $rule->tracking_pixel_id,
                ]);
            }
        }

        Schema::dropIfExists('tracking_pixel_rules');
    }

    private function migratePageAttachments(string $pivot, string $targetType, string $targetTable, string $targetKey): void
    {
        if (! Schema::hasTable($pivot)) {
            return;
        }

        $now = now('UTC');
        $attachments = DB::table($pivot)
            ->join('tracking_pixels', 'tracking_pixels.id', '=', "{$pivot}.tracking_pixel_id")
            ->join($targetTable, "{$targetTable}.id", '=', "{$pivot}.{$targetKey}")
            ->whereColumn('tracking_pixels.workspace_id', "{$targetTable}.workspace_id")
            ->get([
                'tracking_pixels.workspace_id',
                "{$pivot}.tracking_pixel_id",
                "{$pivot}.{$targetKey} as target_id",
            ]);

        foreach ($attachments as $attachment) {
            DB::table('tracking_pixel_rules')->insertOrIgnore([
                'workspace_id' => $attachment->workspace_id,
                'tracking_pixel_id' => $attachment->tracking_pixel_id,
                'target_type' => $targetType,
                'target_id' => $attachment->target_id,
                'trigger' => 'page_view',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
