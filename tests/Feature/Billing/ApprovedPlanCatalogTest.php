<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovedPlanCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_matches_approved_prices_and_all_resource_limits(): void
    {
        $columns = ['monthly_price', 'annual_price', 'link_limit', 'folder_limit', 'qr_campaign_limit', 'bio_page_limit', 'domain_limit', 'pixel_limit', 'member_limit', 'webhook_limit', 'link_page_limit', 'api_key_limit', 'storage_byte_limit'];
        $expected = [
            'free' => [0, 0, 25, 5, 5, 1, 0, 0, 1, 0, 1, 0, 100 * 1024 * 1024],
            'pro' => [2900, 29000, 500, 100, 250, 10, 3, 10, 5, 5, 25, 5, 10 * 1024 * 1024 * 1024],
            'business' => [9900, 99000, null, null, null, null, null, null, null, null, null, null, null],
        ];

        foreach ($expected as $code => $values) {
            $plan = Plan::query()->where('code', $code)->sole();
            foreach ($columns as $index => $column) {
                $this->assertSame($values[$index], $plan->getAttribute($column), "{$code}.{$column}");
            }
        }
    }

    public function test_free_workspace_cannot_create_a_sixth_folder(): void
    {
        $user = User::factory()->create();
        $workspace = $user->currentWorkspace;
        foreach (range(1, 5) as $index) {
            $workspace->folders()->create(['user_id' => $user->id, 'name' => "Folder {$index}"]);
        }

        $this->actingAs($user)->post('/dashboard/folders', ['name' => 'Over limit'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(5, $workspace->folders()->count());
    }

    public function test_free_workspace_cannot_create_an_api_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/account-settings/api-keys', [
            'name' => 'Not included',
            'abilities' => ['links:read'],
        ])->assertSessionHasErrors('plan');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
