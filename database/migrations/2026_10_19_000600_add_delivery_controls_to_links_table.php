<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table): void {
            $table->string('access_code')->nullable()->after('folder_id');
            $table->timestamp('available_from')->nullable()->after('is_active');
            $table->timestamp('expires_at')->nullable()->after('available_from');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table): void {
            $table->dropColumn(['access_code', 'available_from', 'expires_at']);
        });
    }
};
