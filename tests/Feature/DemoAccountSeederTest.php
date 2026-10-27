<?php

namespace Tests\Feature;

use App\Models\LinkPage;
use App\Models\TrackingPixel;
use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_link_page_pixel_is_seeded_as_a_page_view_rule(): void
    {
        $this->seed(DemoAccountSeeder::class);

        $user = User::query()->where('email', DemoAccountSeeder::USER_EMAIL)->firstOrFail();
        $workspace = $user->personalWorkspace()->firstOrFail();
        $linkPage = LinkPage::query()
            ->where('workspace_id', $workspace->id)
            ->where('slug', 'demo-page')
            ->firstOrFail();
        $pixel = TrackingPixel::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', 'Demo Meta pixel')
            ->firstOrFail();

        $this->assertDatabaseHas('tracking_pixel_rules', [
            'workspace_id' => $workspace->id,
            'tracking_pixel_id' => $pixel->id,
            'target_type' => 'link_page',
            'target_id' => $linkPage->id,
            'trigger' => 'page_view',
        ]);
    }
}
