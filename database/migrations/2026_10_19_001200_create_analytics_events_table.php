<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bio_page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bio_link_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 64);
            $table->timestamp('occurred_at');
            $table->char('visitor_hash', 64);
            $table->string('visitor_key_version', 32);
            $table->string('referrer_host', 253)->nullable();
            $table->string('browser', 32)->nullable();
            $table->string('platform', 32)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['link_id', 'occurred_at']);
            $table->index(['bio_page_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
