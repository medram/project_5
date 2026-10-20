<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->text('secret');
            $table->json('events');
            $table->unsignedInteger('revision')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamp('secret_rotated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // Deliveries retain the webhook id without a foreign key so audit
        // history survives webhook deletion, matching analytics retention.
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('webhook_id')->nullable();
            $table->unsignedInteger('revision');
            $table->uuid('event_uuid');
            $table->string('event_name', 64);
            $table->text('body');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('state', 16)->default('pending');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('response_excerpt', 500)->nullable();
            $table->string('failure_detail', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'state']);
            $table->index(['event_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
