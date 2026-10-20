<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hostname', 253)->unique();
            $table->string('verification_token', 64)->unique();
            $table->string('status', 20)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('links', function (Blueprint $table): void {
            $table->foreignId('custom_domain_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('custom_domain_id');
        });

        Schema::dropIfExists('custom_domains');
    }
};
