<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table): void {
            $table->foreignId('folder_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
            $table->index('folder_id');
        });
    }

    public function down(): void
    {
        // The explicit folder index must go first: SQLite rebuilds the table on
        // dropColumn and fails while an index still references the column.
        if (Schema::hasIndex('links', 'links_folder_id_index')) {
            Schema::table('links', function (Blueprint $table): void {
                $table->dropIndex(['folder_id']);
            });
        }

        Schema::table('links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('folder_id');
        });
    }
};
