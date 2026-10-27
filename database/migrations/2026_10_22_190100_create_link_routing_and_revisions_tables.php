<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_routing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('rule_type', 16);
            $table->string('match_value', 128);
            $table->string('target_url', 2048);
            $table->timestamps();

            $table->unique(['link_id', 'position']);
            $table->index(['workspace_id', 'link_id']);
        });

        Schema::create('link_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 16);
            $table->json('snapshot');
            $table->string('review_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['link_id', 'revision_number']);
            $table->index(['workspace_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('link_routing_rules')->exists() || DB::table('link_revisions')->exists()) {
            throw new LogicException('Cannot remove link routing rules or revision history while campaign data exists.');
        }

        Schema::dropIfExists('link_revisions');
        Schema::dropIfExists('link_routing_rules');
    }
};
