<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_page_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_page_block_id')->constrained('link_page_blocks')->cascadeOnDelete();
            $table->longText('responses');
            $table->timestampTz('consented_at');
            $table->timestampTz('retention_expires_at');
            $table->timestamps();

            $table->index(['workspace_id', 'link_page_id', 'created_at', 'id']);
            $table->index('retention_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_page_leads');
    }
};
