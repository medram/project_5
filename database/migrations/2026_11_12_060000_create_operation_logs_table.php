<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('level', 16)->default('info');
            $table->string('channel', 40)->default('app');
            $table->string('event', 80);
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['level', 'created_at']);
            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
