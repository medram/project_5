<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 160);
            $table->longText('body');
            $table->string('state', 20)->default('draft');
            $table->string('placement', 20)->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->foreignId('og_media_id')->nullable()->constrained('content_media')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'published_at']);
        });

        Schema::table('content_media', function (Blueprint $table): void {
            $table->string('deletion_reason', 500)->nullable()->after('scan_state');
        });
    }

    public function down(): void
    {
        Schema::table('content_media', function (Blueprint $table): void {
            $table->dropColumn('deletion_reason');
        });

        Schema::dropIfExists('custom_pages');
    }
};