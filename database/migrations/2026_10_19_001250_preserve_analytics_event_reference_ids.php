<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing installations created analytics_events with null-on-delete
     * foreign keys. Analytics retention must preserve the original content IDs,
     * so remove only those foreign key constraints; the indexed columns remain.
     */
    public function up(): void
    {
        foreach (['link_id', 'bio_page_id', 'bio_link_id'] as $column) {
            if (! Schema::hasForeignKey('analytics_events', [$column])) {
                continue;
            }

            Schema::table('analytics_events', function (Blueprint $table) use ($column): void {
                $table->dropForeign([$column]);
            });
        }
    }

    public function down(): void
    {
        // Recreating the constraints can erase retained IDs when content is deleted.
    }
};
