<?php

namespace Tests\Feature\Billing;

use App\Enums\MediaPurpose;
use App\Enums\PlanMetric;
use App\Models\BillingHealthRecord;
use App\Models\ContentMedia;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceApiToken;
use App\Services\Billing\EffectiveWorkspacePlan;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Billing\WorkspacePlanOverrides;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase8BillingCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_override_then_provider_subscription_then_free_resolution_order(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $pro = Plan::query()->where('code', 'pro')->sole();
        $business = Plan::query()->where('code', 'business')->sole();
        $pro->update(['stripe_monthly_price_id' => 'price_pro_month', 'stripe_yearly_price_id' => 'price_pro_year']);
        $business->update(['stripe_monthly_price_id' => 'price_business_month']);

        $this->subscribe($workspace, 'price_pro_month');

        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace)->code);

        $override = app(WorkspacePlanOverrides::class)->assign(
            $workspace,
            $business,
            $user,
            'support_grant',
            'Enterprise pilot.',
        );

        $resolved = app(EffectiveWorkspacePlan::class)->for($workspace);
        $this->assertSame('business', $resolved['plan']->code);
        $this->assertSame('support_grant', $resolved['source']);
        $this->assertSame($override->id, $resolved['override']->id);

        app(WorkspacePlanOverrides::class)->revoke($override, $user, 'Pilot ended.');
        $resolved = app(EffectiveWorkspacePlan::class)->for($workspace);
        $this->assertSame('pro', $resolved['plan']->code);
        $this->assertSame('stripe', $resolved['source']);

        DB::table('subscriptions')->where('workspace_id', $workspace->id)->update([
            'stripe_status' => 'canceled',
            'ends_at' => now()->addDays(3),
        ]);
        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);

        DB::table('subscriptions')->where('workspace_id', $workspace->id)->update([
            'ends_at' => now()->subMinute(),
        ]);
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
    }

    public function test_future_and_expired_overrides_do_not_apply(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $pro = Plan::query()->where('code', 'pro')->sole();

        app(WorkspacePlanOverrides::class)->assign(
            $workspace,
            $pro,
            $user,
            'support_grant',
            'Future grant.',
            startsAt: now()->addDay(),
        );
        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace)->code);

        DB::table('workspace_plan_overrides')->update([
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
    }

    public function test_trialing_provider_subscription_maps_to_plan_while_unknown_prices_fail_closed(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $pro = Plan::query()->where('code', 'pro')->sole();
        $pro->update(['stripe_yearly_price_id' => 'price_pro_year']);

        $this->subscribe($workspace, 'price_unmapped', status: 'trialing');

        $this->assertSame('free', app(WorkspaceUsage::class)->planFor($workspace)->code);
        $health = BillingHealthRecord::query()->where('workspace_id', $workspace->id)->sole();
        $this->assertSame('unmapped_stripe_price', $health->type);
        $this->assertSame('price_unmapped', $health->stripe_price);
        $this->assertNull($health->resolved_at);

        DB::table('subscriptions')->where('workspace_id', $workspace->id)->update([
            'stripe_price' => 'price_pro_year',
        ]);

        $this->assertSame('pro', app(WorkspaceUsage::class)->planFor($workspace->fresh())->code);
        $this->assertNotNull($health->fresh()->resolved_at);
    }

    public function test_storage_bytes_count_retained_soft_deleted_media_and_block_only_new_overage(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        Plan::query()->where('code', 'free')->sole()->update(['storage_byte_limit' => 1000]);

        $this->media($workspace, 300, 'active-image');
        $this->media($workspace, 600, 'soft-deleted-image')->delete();

        $usage = app(WorkspaceUsage::class);
        $this->assertSame(900, $usage->metricFor($workspace, PlanMetric::StorageBytes)['used']);

        $this->expectException(ValidationException::class);
        app(EnforcesPlanLimits::class)->create(
            $workspace,
            PlanMetric::StorageBytes,
            fn (): string => 'must-not-run',
            101,
        );
    }

    public function test_storage_quota_allows_exact_remaining_bytes_and_downgrades_preserve_media(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        Plan::query()->where('code', 'free')->sole()->update(['storage_byte_limit' => 1000]);
        $media = $this->media($workspace, 950, 'retained-image');

        $result = app(EnforcesPlanLimits::class)->create(
            $workspace,
            PlanMetric::StorageBytes,
            fn (): string => 'stored',
            50,
        );

        $this->assertSame('stored', $result);
        $this->assertSame(950, ContentMedia::withTrashed()->where('workspace_id', $workspace->id)->sum('byte_size'));

        app(WorkspacePlanOverrides::class)->assign($workspace, Plan::query()->where('code', 'free')->sole(), $user, 'support_grant', 'Force free.');
        $this->assertDatabaseHas('content_media', ['id' => $media->id]);
        $this->assertSame(950, app(WorkspaceUsage::class)->metricFor($workspace, PlanMetric::StorageBytes)['used']);
    }

    public function test_folder_bio_page_and_api_key_metrics_use_effective_plan_limits(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        app(WorkspaceUsage::class)->assignPlan($workspace, Plan::query()->where('code', 'pro')->sole());

        $usage = app(WorkspaceUsage::class)->for($workspace);
        $this->assertSame(100, $usage['folders']['limit']);
        $this->assertSame(10, $usage['bioPages']['limit']);
        $this->assertSame(5, $usage['apiKeys']['limit']);
        $this->assertSame(250, $usage['qrCampaigns']['limit']);

        $workspace->folders()->create(['user_id' => $user->id, 'name' => 'One']);
        $workspace->bioPages()->create(['user_id' => $user->id, 'handle' => 'metric-bio', 'display_name' => 'Metric']);
        (new WorkspaceApiToken)->forceFill([
            'name' => 'Active key',
            'token' => hash('sha256', 'active-token'),
            'abilities' => ['links:read'],
            'workspace_id' => $workspace->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'expires_at' => null,
        ])->save();
        (new WorkspaceApiToken)->forceFill([
            'name' => 'Expired key',
            'token' => hash('sha256', 'expired-token'),
            'abilities' => ['links:read'],
            'workspace_id' => $workspace->id,
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'expires_at' => now()->subDay(),
        ])->save();

        $usage = app(WorkspaceUsage::class)->for($workspace->fresh());
        $this->assertSame(1, $usage['folders']['used']);
        $this->assertSame(1, $usage['bioPages']['used']);
        $this->assertSame(1, $usage['apiKeys']['used']);
    }

    public function test_free_plan_blocks_folder_bio_page_and_api_key_creation(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();

        $workspace->bioPages()->create(['user_id' => $user->id, 'handle' => 'existing-bio', 'display_name' => 'Existing']);

        $this->expectException(ValidationException::class);
        app(EnforcesPlanLimits::class)->create(
            $workspace,
            PlanMetric::BioPages,
            fn (): string => 'must-not-run',
        );
    }

    private function subscribe(Workspace $workspace, string $price, string $status = 'active', mixed $endsAt = null): void
    {
        DB::table('subscriptions')->insert([
            'workspace_id' => $workspace->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.str()->random(20),
            'stripe_status' => $status,
            'stripe_price' => $price,
            'quantity' => 1,
            'trial_ends_at' => $status === 'trialing' ? now()->addDays(7) : null,
            'ends_at' => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function media(Workspace $workspace, int $bytes, string $name): ContentMedia
    {
        return ContentMedia::query()->create([
            'workspace_id' => $workspace->getKey(),
            'uploaded_by' => $workspace->owner_id,
            'public_id' => (string) str()->uuid(),
            'disk' => 'local',
            'path' => "test/{$name}.png",
            'original_name' => "{$name}.png",
            'mime_type' => 'image/png',
            'byte_size' => $bytes,
            'sha256' => hash('sha256', $name),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'purpose' => MediaPurpose::Image,
            'expires_at' => now()->addDay(),
        ]);
    }
}
