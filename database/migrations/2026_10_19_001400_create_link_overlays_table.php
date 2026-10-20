<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_overlays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('heading', 160);
            $table->string('body', 500)->nullable();
            $table->string('cta_label', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('link_overlay_tracking_pixel', function (Blueprint $table): void {
            $table->foreignId('link_overlay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_pixel_id')->constrained()->cascadeOnDelete();

            $table->unique(['link_overlay_id', 'tracking_pixel_id']);
        });

        Schema::table('links', function (Blueprint $table): void {
            $table->foreignId('link_overlay_id')
                ->nullable()
                ->after('custom_domain_id')
                ->constrained('link_overlays')
                ->nullOnDelete();
        });

        // Analytics retention preserves exact content references after deletion,
        // so these columns intentionally carry no content foreign keys.
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('link_overlay_id')->nullable()->after('link_page_item_id');
            $table->index(['link_overlay_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropIndex(['link_overlay_id', 'occurred_at']);
            $table->dropColumn('link_overlay_id');
        });

        Schema::table('links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('link_overlay_id');
        });

        Schema::dropIfExists('link_overlay_tracking_pixel');
        Schema::dropIfExists('link_overlays');
    }
};
