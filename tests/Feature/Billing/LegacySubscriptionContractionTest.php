<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySubscriptionContractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_down_reconstructs_legacy_rows_and_up_preserves_effective_plan_and_drops_the_table(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->firstOrFail();
        app(WorkspaceUsage::class)->assignPlan($workspace, Plan::query()->where('code', 'pro')->sole());
        $migration = require database_path('migrations/2026_11_12_080000_contract_legacy_workspace_subscriptions.php');

        $this->assertFalse(Schema::hasTable('workspace_subscriptions'));
        $migration->down();

        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'plan_id' => Plan::query()->where('code', 'pro')->value('id'),
            'source' => 'manual',
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasTable('workspace_subscriptions'));
        $effective = app(WorkspaceUsage::class)->effectivePlanFor($workspace);
        $this->assertSame('pro', $effective['plan']->code);
        $this->assertSame('manual', $effective['source']);
        $this->assertDatabaseHas('workspace_plan_overrides', [
            'workspace_id' => $workspace->id,
            'plan_id' => Plan::query()->where('code', 'pro')->value('id'),
            'source' => 'manual',
        ]);
    }

    public function test_free_workspace_reconstructs_a_free_legacy_row_without_an_override(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->firstOrFail();
        $migration = require database_path('migrations/2026_11_12_080000_contract_legacy_workspace_subscriptions.php');

        $migration->down();

        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'plan_id' => Plan::query()->where('code', 'free')->value('id'),
            'status' => 'active',
        ]);
        $this->assertSame(0, DB::table('workspace_plan_overrides')->where('workspace_id', $workspace->id)->count());

        $migration->up();
        $this->assertFalse(Schema::hasTable('workspace_subscriptions'));
    }
}
