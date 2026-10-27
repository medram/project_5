<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('route', 255);
            $table->string('method', 8);
            $table->unsignedSmallInteger('status_code');
            $table->timestamps();
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('idempotency_key', 255);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['workspace_id', 'token_id', 'idempotency_key'], 'api_idempotency_workspace_token_key_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_keys');
        Schema::dropIfExists('api_audit_logs');
    }
};
