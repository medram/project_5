<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'code' => 'creator', 'name' => 'Creator', 'description' => 'For creators',
            'sort_order' => 5, 'featured' => false, 'is_active' => true,
            'limits' => [
                'links' => null, 'folders' => 5, 'qr_campaigns' => 3, 'bio_pages' => 2,
                'domains' => 0, 'pixels' => 2, 'members' => 1, 'webhooks' => 0,
                'link_pages' => 2, 'api_keys' => 0, 'storage_bytes' => 1000,
            ],
        ];
    }

    public function test_custom_plan_override_enforces_and_updates_qr_quota_without_deleting_campaigns(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace;
        $payload = $this->payload();

        $this->actingAs($admin)->post('/admin/plans', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan = Plan::query()->where('code', 'creator')->sole();
        $this->assertNull($plan->link_limit);
        $this->assertSame(0, $plan->domain_limit);
        $this->assertSame(3, $plan->qr_campaign_limit);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'create_plan', 'subject_id' => $plan->id]);

        $this->post("/admin/subscriptions/{$workspace->id}/overrides", [
            'plan' => 'creator', 'source' => 'support_grant', 'reason' => 'Test allocation',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner);
        for ($i = 1; $i <= 3; $i++) {
            $this->post('/dashboard/qr-codes', ['destination_url' => "https://example.com/{$i}"])
                ->assertSessionHasNoErrors();
        }
        $this->from('/dashboard/qr-codes')->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/4',
        ])->assertSessionHasErrors('plan');
        $this->assertSame(3, $workspace->qrCodes()->count());

        $payload['limits']['qr_campaigns'] = 4;
        $this->actingAs($admin)->patch('/admin/plans/creator', $payload)->assertSessionHasNoErrors();
        $this->assertSame(3, $workspace->qrCodes()->count());
        $this->actingAs($owner)->post('/dashboard/qr-codes', [
            'destination_url' => 'https://example.com/4',
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, $workspace->qrCodes()->count());
    }

    public function test_free_plan_cannot_be_deactivated_or_receive_prices_through_metadata(): void
    {
        $payload = $this->payload();
        $payload['is_active'] = false;
        $payload['stripe_monthly_price_id'] = 'price_untrusted';
        $this->actingAs(User::factory()->admin()->create())
            ->patch('/admin/plans/free', $payload)
            ->assertSessionHasErrors('is_active');
        $this->assertDatabaseHas('plans', [
            'code' => 'free', 'is_active' => true, 'monthly_price' => 0,
            'stripe_monthly_price_id' => null,
        ]);
    }

    public function test_creation_rejects_invalid_codes_and_limits_and_unauthorized_writes(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = $this->payload();
        $this->actingAs(User::factory()->create())->post('/admin/plans', $payload)->assertForbidden();
        $this->actingAs(User::factory()->support()->create())->post('/admin/plans', $payload)->assertForbidden();
        $this->actingAs($admin);

        $missing = $payload;
        unset($missing['limits']['links']);
        $this->post('/admin/plans', $missing)->assertSessionHasErrors('limits.links');
        $negative = $payload;
        $negative['limits']['domains'] = -1;
        $this->post('/admin/plans', $negative)->assertSessionHasErrors('limits.domains');
        $overflow = $payload;
        $overflow['limits']['qr_campaigns'] = 4294967296;
        $overflow['limits']['storage_bytes'] = 9007199254740992;
        $this->post('/admin/plans', $overflow)->assertSessionHasErrors(['limits.qr_campaigns', 'limits.storage_bytes']);
        foreach (['free', 'pro', 'business', 'UPPER', 'a', 'invalid code'] as $code) {
            $this->post('/admin/plans', [...$payload, 'code' => $code])->assertSessionHasErrors('code');
        }
        $this->post('/admin/plans', $payload)->assertSessionHasNoErrors();
        $this->post('/admin/plans', $payload)->assertSessionHasErrors('code');
        $this->assertSame(1, Plan::query()->where('code', 'creator')->count());
    }
}
