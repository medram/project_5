<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;


    public function test_owner_can_create_a_workspace_bound_key_that_is_hashed_and_revealed_once(): void
    {
        $owner = User::factory()->create();
        $workspace = $owner->currentWorkspace()->firstOrFail();
        $expiresAt = now()->addDays(30)->format('Y-m-d\TH:i');

        $this->withoutVite();
        $plainTextToken = $this->createApiKey($owner, [
            'name' => 'CI deploy key',
            'abilities' => ['links:read', 'analytics:read'],
            'expires_at' => $expiresAt,
        ]);
        [$tokenId, $secret] = explode('|', $plainTextToken, 2);
        $storedToken = DB::table('personal_access_tokens')->where('id', $tokenId)->first();

        $this->assertNotNull($storedToken);
        $this->assertSame($workspace->id, (int) $storedToken->workspace_id);
        $this->assertSame($owner->id, (int) $storedToken->tokenable_id);
        $this->assertSame(hash('sha256', $secret), $storedToken->token);
        $this->assertSame(['links:read', 'analytics:read'], json_decode($storedToken->abilities, true));
        $this->assertSame(
            Carbon::parse($expiresAt)->format('Y-m-d H:i'),
            Carbon::parse($storedToken->expires_at)->format('Y-m-d H:i'),
        );
        $this->assertNull($storedToken->last_used_at);

        $this->actingAs($owner)
            ->get('/account-settings/api-keys')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/api-keys/index')
                ->has('keys', 1)
                ->where('keys.0.name', 'CI deploy key')
                ->where('keys.0.abilities', ['links:read', 'analytics:read'])
                ->where('oneTimeToken', $plainTextToken)
                ->missing('keys.0.token')
                ->missing('keys.0.hash')
            );

        $this->actingAs($owner)
            ->get('/account-settings/api-keys')
            ->assertInertia(fn (Assert $page) => $page
                ->where('oneTimeToken', null)
                ->missing('keys.0.token')
            );
    }

    public function test_workspace_admin_can_create_keys_but_members_cannot_manage_them(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner, 'api-key-role-policy');
        $workspace->members()->attach($admin->id, ['role' => 'admin']);
        $workspace->members()->attach($member->id, ['role' => 'member']);
        $this->activateWorkspace($admin, $workspace);
        $this->activateWorkspace($member, $workspace);

        $this->createApiKey($admin, [
            'name' => 'Admin integration',
            'abilities' => ['links:read'],
            'expires_at' => null,
        ]);

        $this->withoutVite();
        $this->actingAs($member)
            ->get('/account-settings/api-keys')
            ->assertForbidden();

        $this->actingAs($member)
            ->post('/account-settings/api-keys', [
                'name' => 'Member key',
                'abilities' => ['links:read'],
                'expires_at' => null,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_keys_remain_bound_to_their_workspace_after_switching_and_cannot_be_revoked_cross_workspace(): void
    {
        $owner = User::factory()->create();
        $firstWorkspace = $owner->currentWorkspace()->firstOrFail();
        $secondWorkspace = $this->teamWorkspace($owner, 'api-key-second-workspace');
        $this->activateWorkspace($owner, $firstWorkspace);
        $plainTextToken = $this->createApiKey($owner, [
            'name' => 'Workspace one key',
            'abilities' => ['links:read'],
            'expires_at' => null,
        ]);
        [$tokenId] = explode('|', $plainTextToken, 2);

        $this->activateWorkspace($owner, $secondWorkspace);
        $this->withoutVite();
        $this->actingAs($owner)
            ->get('/account-settings/api-keys')
            ->assertInertia(fn (Assert $page) => $page
                ->has('keys', 0)
                ->where('oneTimeToken', null));

        $this->actingAs($owner)
            ->delete("/account-settings/api-keys/{$tokenId}")
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenId,
            'workspace_id' => $firstWorkspace->id,
        ]);

        $this->activateWorkspace($owner, $firstWorkspace);
        $this->actingAs($owner)
            ->delete("/account-settings/api-keys/{$tokenId}")
            ->assertRedirect('/account-settings/api-keys');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_token_abilities_are_intersected_and_successful_use_updates_last_used_at(): void
    {
        $owner = User::factory()->create();
        $plainTextToken = $this->createApiKey($owner, [
            'name' => 'Read-only key',
            'abilities' => ['links:read', 'analytics:read'],
            'expires_at' => null,
        ]);
        Auth::logout();


        Route::middleware(['auth:sanctum', 'abilities:links:read'])
            ->get('/__test/api-key/read', fn (Request $request) => response()->json([
                'canReadLinks' => $request->user()->tokenCan('links:read'),
                'canWriteLinks' => $request->user()->tokenCan('links:write'),
            ]));
        Route::middleware(['auth:sanctum', 'abilities:links:write'])
            ->get('/__test/api-key/write', fn () => response()->json(['ok' => true]));

        $this->withToken($plainTextToken)
            ->getJson('/__test/api-key/read')
            ->assertOk()
            ->assertJson([
                'canReadLinks' => true,
                'canWriteLinks' => false,
            ]);

        $this->withToken($plainTextToken)
            ->getJson('/__test/api-key/write')
            ->assertForbidden();

        $tokenId = explode('|', $plainTextToken, 2)[0];
        $this->assertNotNull(DB::table('personal_access_tokens')->where('id', $tokenId)->value('last_used_at'));
    }

    public function test_expired_tokens_are_rejected_by_sanctum(): void
    {
        $owner = User::factory()->create();
        $plainTextToken = $this->createApiKey($owner, [
            'name' => 'Short-lived key',
            'abilities' => ['links:read'],
            'expires_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);
        Auth::logout();


        Route::middleware('auth:sanctum')
            ->get('/__test/api-key/expiry', fn () => response()->json(['ok' => true]));

        $this->withToken($plainTextToken)
            ->getJson('/__test/api-key/expiry')
            ->assertOk();
        $tokenId = explode('|', $plainTextToken, 2)[0];
        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->update(['expires_at' => now()->subMinute()]);
        Auth::forgetGuards();




        $this->withToken($plainTextToken)
            ->getJson('/__test/api-key/expiry')
            ->assertUnauthorized();
    }

    public function test_empty_or_unknown_abilities_and_past_expiry_are_rejected(): void
    {
        $owner = User::factory()->create();
        $this->activateWorkspace($owner, $owner->currentWorkspace()->firstOrFail());

        $this->actingAs($owner)
            ->from('/account-settings/api-keys')
            ->post('/account-settings/api-keys', [
                'name' => 'No scopes',
                'abilities' => [],
                'expires_at' => null,
            ])
            ->assertSessionHasErrors('abilities');

        $this->actingAs($owner)
            ->from('/account-settings/api-keys')
            ->post('/account-settings/api-keys', [
                'name' => 'Unknown scope',
                'abilities' => ['*'],
                'expires_at' => null,
            ])
            ->assertSessionHasErrors('abilities.0');

        $this->actingAs($owner)
            ->from('/account-settings/api-keys')
            ->post('/account-settings/api-keys', [
                'name' => 'Expired immediately',
                'abilities' => ['links:read'],
                'expires_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('expires_at');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function createApiKey(User $user, array $attributes): string
    {
        $this->actingAs($user)
            ->post('/account-settings/api-keys', $attributes)
            ->assertRedirect('/account-settings/api-keys');

        $token = session('apiKeyToken');
        $this->assertIsString($token);

        return $token;
    }

    private function teamWorkspace(User $owner, string $slug): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'API Key Team',
            'slug' => $slug,
            'personal_owner_user_id' => null,
            'owner_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Workspace::query()->findOrFail($workspaceId);
    }

    private function activateWorkspace(User $user, Workspace $workspace): void
    {
        app(WorkspaceContext::class)->activate($user, $workspace);
    }
}
