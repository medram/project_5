<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable();
            $table->string('utm_source', 512)->nullable();
            $table->string('utm_medium', 512)->nullable();
            $table->string('utm_campaign', 512)->nullable();
            $table->string('utm_term', 512)->nullable();
            $table->string('utm_content', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropColumn([
                'country_code',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
            ]);
        });
    }
};
