<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bio_pages', function (Blueprint $table): void {
            $table->json('appearance')->default('{}');
        });

        Schema::table('bio_links', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->boolean('is_pinned')->default(false)->index();
        });

        Schema::create('bio_content_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bio_page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->json('content');
            $table->foreignId('content_media_id')->nullable()->constrained('content_media')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamps();
            $table->index(['bio_page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bio_content_blocks');
        Schema::table('bio_links', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'ends_at', 'is_pinned']);
        });
        Schema::table('bio_pages', function (Blueprint $table): void {
            $table->dropColumn('appearance');
        });
    }
};
