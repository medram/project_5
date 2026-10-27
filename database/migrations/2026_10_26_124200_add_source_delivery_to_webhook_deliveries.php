<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->unsignedBigInteger('source_delivery_id')->nullable()->after('webhook_id');
            $table->index('source_delivery_id');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['source_delivery_id']);
            $table->dropColumn('source_delivery_id');
        });
    }
};
