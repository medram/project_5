<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->char('visitor_hash', 64)->nullable()->change();
            $table->string('visitor_key_version', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('analytics_events')->whereNull('visitor_hash')->orWhereNull('visitor_key_version')->exists()) {
            throw new LogicException('Remove server-side events without visitor dimensions before rolling back this migration.');
        }

        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->char('visitor_hash', 64)->nullable(false)->change();
            $table->string('visitor_key_version', 32)->nullable(false)->change();
        });
    }
};
