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
            $table->string('display_name')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('content_media')->whereNotNull('display_name')->exists()) {
            throw new RuntimeException('Cannot roll back media display names while any are populated.');
        }

        Schema::table('content_media', function (Blueprint $table): void {
            $table->dropColumn('display_name');
        });
    }
};
