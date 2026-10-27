<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WORKSPACE_OWNED_TABLES = [
        'links',
        'bio_pages',
        'folders',
        'custom_domains',
        'tracking_pixels',
        'link_pages',
        'link_overlays',
        'webhooks',
        'analytics_events',
    ];

    public function up(): void
    {
        // Complete owner-cardinality preflight before any schema change so a
        // failure leaves the schema untouched and the migration stays retryable.
        $canonicalOwners = [];
        $workspaces = DB::table('workspaces')->select('id')->orderBy('id')->get();
        foreach ($workspaces as $workspace) {
            $owners = DB::table('workspace_user')
                ->where('workspace_id', $workspace->id)
                ->where('role', 'owner')
                ->pluck('user_id');

            if ($owners->count() !== 1) {
                throw new RuntimeException("Cannot establish canonical owner for workspace [{$workspace->id}]: expected exactly one owner membership.");
            }

            $canonicalOwners[$workspace->id] = $owners->first();
        }

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->restrictOnDelete();
        });

        foreach ($canonicalOwners as $workspaceId => $ownerUserId) {
            DB::table('workspaces')->where('id', $workspaceId)->update(['owner_id' => $ownerUserId]);
        }

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable(false)->change();
        });

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->unsignedBigInteger('user_id')->nullable()->change();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            if (DB::table($tableName)->whereNull('user_id')->exists()) {
                throw new LogicException("Cannot restore required creator attribution while [{$tableName}] contains unattributed rows.");
            }
        }

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
