<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_scans', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable();
            $table->string('browser', 32)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('referrer_host', 253)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('qr_scans', function (Blueprint $table): void {
            $table->dropColumn([
                'country_code',
                'browser',
                'platform',
                'referrer_host',
            ]);
        });
    }
};
