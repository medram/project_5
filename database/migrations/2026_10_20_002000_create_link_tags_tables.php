<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->char('color', 7)->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('link_link_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_tag_id')->constrained('link_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['link_id', 'link_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_link_tag');
        Schema::dropIfExists('link_tags');
    }
};
