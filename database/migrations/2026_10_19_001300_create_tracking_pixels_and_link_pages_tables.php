<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_pixels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('provider', 32);
            $table->string('provider_id', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('link_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 32)->unique();
            $table->string('title', 160);
            $table->string('description', 500)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_published']);
        });

        Schema::create('link_page_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('destination_url', 2048);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['link_page_id', 'is_active', 'position']);
        });

        Schema::create('link_page_tracking_pixel', function (Blueprint $table): void {
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_pixel_id')->constrained()->cascadeOnDelete();

            $table->unique(['link_page_id', 'tracking_pixel_id']);
        });

        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('link_page_id')->nullable()->after('bio_link_id');
            $table->unsignedBigInteger('link_page_item_id')->nullable()->after('link_page_id');
            $table->index(['link_page_id', 'occurred_at']);
            $table->index(['link_page_item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropIndex(['link_page_id', 'occurred_at']);
            $table->dropIndex(['link_page_item_id', 'occurred_at']);
            $table->dropColumn(['link_page_id', 'link_page_item_id']);
        });

        Schema::dropIfExists('link_page_tracking_pixel');
        Schema::dropIfExists('link_page_items');
        Schema::dropIfExists('link_pages');
        Schema::dropIfExists('tracking_pixels');
    }
};
