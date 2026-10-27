<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticatedRouteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_routes_use_the_canonical_paths_without_private_aliases(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();

        foreach ([
            '/dashboard/links',
            '/dashboard/folders',
            '/dashboard/biolinks',
            '/dashboard/insights',
            '/dashboard/qr-codes',
            '/dashboard/link-pages',
            '/dashboard/link-overlays',
            '/dashboard/pixels',
            '/dashboard/custom-domains',
            '/account-settings/general',
            '/account-settings/appearance',
            '/account-settings/workspaces',
            '/account-settings/billing',
            '/account-settings/webhooks',
        ] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }

        $this->actingAs($user)
            ->get('/account-settings/security')
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/dashboard/links');

        foreach ([
            '/links',
            '/folders',
            '/bio-pages',
            '/analytics',
            '/link-pages',
            '/overlays',
            '/tracking-pixels',
            '/custom-domains',
            '/settings/profile',
            '/settings/security',
            '/settings/appearance',
            '/settings',
            '/billing',
            '/webhooks',
        ] as $path) {
            $this->actingAs($user)->get($path)->assertNotFound();
        }
    }
}
