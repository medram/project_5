<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_pages', function (Blueprint $table): void {
            $table->string('template', 16)->default('standard');
        });

        Schema::create('link_page_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->json('content');
            $table->foreignId('content_media_id')->nullable()->constrained('content_media')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestamps();

            $table->index(['link_page_id', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_page_blocks');

        Schema::table('link_pages', function (Blueprint $table): void {
            $table->dropColumn('template');
        });
    }
};
