<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bio_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bio_page_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('destination_url', 2048);
            $table->unsignedInteger('position');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['bio_page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bio_links');
    }
};
