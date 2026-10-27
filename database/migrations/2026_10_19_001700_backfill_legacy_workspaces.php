<?php

use App\Services\Workspaces\BackfillLegacyWorkspaces;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(BackfillLegacyWorkspaces::class)->run();
    }

    public function down(): void
    {
        // Workspace IDs are additive and the legacy creator IDs remain intact.
        // The expand migration owns rollback and refuses lossy collaboration rollbacks.
    }
};
