<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_overlays', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->after('is_active');
            $table->timestamp('expires_at')->nullable()->after('starts_at');
            $table->unsignedSmallInteger('display_delay_seconds')->default(0)->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('link_overlays', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'expires_at', 'display_delay_seconds']);
        });
    }
};
