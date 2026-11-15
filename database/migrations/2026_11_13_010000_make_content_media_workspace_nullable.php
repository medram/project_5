<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_media', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('content_media')->whereNull('workspace_id')->exists()) {
            throw new LogicException('Cannot require a workspace while platform assets exist.');
        }

        Schema::table('content_media', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->nullable(false)->change();
        });
    }
};
