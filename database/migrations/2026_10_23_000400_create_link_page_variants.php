<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_page_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedTinyInteger('weight')->default(0);
            $table->boolean('is_control')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletesTz();

            $table->index(['link_page_id', 'is_active']);
        });

        Schema::table('link_page_items', function (Blueprint $table): void {
            $table->foreignId('link_page_variant_id')
                ->nullable()
                ->constrained('link_page_variants')
                ->nullOnDelete();
            $table->index(['link_page_id', 'link_page_variant_id', 'position']);
        });

        Schema::table('link_page_blocks', function (Blueprint $table): void {
            $table->foreignId('link_page_variant_id')
                ->nullable()
                ->constrained('link_page_variants')
                ->nullOnDelete();
            $table->index(['link_page_id', 'link_page_variant_id', 'position']);
        });

        Schema::table('link_page_leads', function (Blueprint $table): void {
            $table->unsignedBigInteger('link_page_variant_id')->nullable();
            $table->index(['link_page_variant_id', 'created_at']);
        });

        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('link_page_variant_id')->nullable();
            $table->index(['link_page_variant_id', 'occurred_at']);
        });

        $now = now('UTC');
        DB::table('link_pages')->orderBy('id')->chunkById(200, function ($pages) use ($now): void {
            foreach ($pages as $page) {
                $variantId = DB::table('link_page_variants')->insertGetId([
                    'link_page_id' => $page->id,
                    'name' => 'Control',
                    'weight' => 100,
                    'is_control' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('link_page_items')
                    ->where('link_page_id', $page->id)
                    ->update(['link_page_variant_id' => $variantId]);
                DB::table('link_page_blocks')
                    ->where('link_page_id', $page->id)
                    ->update(['link_page_variant_id' => $variantId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropIndex(['link_page_variant_id', 'occurred_at']);
            $table->dropColumn('link_page_variant_id');
        });

        Schema::table('link_page_leads', function (Blueprint $table): void {
            $table->dropIndex(['link_page_variant_id', 'created_at']);
            $table->dropColumn('link_page_variant_id');
        });

        Schema::table('link_page_blocks', function (Blueprint $table): void {
            $table->dropIndex(['link_page_id', 'link_page_variant_id', 'position']);
            $table->dropConstrainedForeignId('link_page_variant_id');
        });

        Schema::table('link_page_items', function (Blueprint $table): void {
            $table->dropIndex(['link_page_id', 'link_page_variant_id', 'position']);
            $table->dropConstrainedForeignId('link_page_variant_id');
        });

        Schema::dropIfExists('link_page_variants');
    }
};
