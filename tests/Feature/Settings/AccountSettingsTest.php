<?php

namespace Tests\Feature\Settings;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_updates_regional_preferences_and_validates_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'fr',
                'country_code' => 'ma',
                'timezone' => 'Africa/Casablanca',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('fr', $user->locale);
        $this->assertSame('MA', $user->country_code);
        $this->assertSame('Africa/Casablanca', $user->timezone);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => 'zz',
                'country_code' => 'ZZ',
                'timezone' => 'Mars/Base',
            ])
            ->assertSessionHasErrors(['locale', 'country_code', 'timezone']);
    }

    public function test_changing_email_resets_verification_and_resends_the_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'changed-address@example.test',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_avatar_upload_uses_private_account_media_and_authorized_delivery(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $peer = User::factory()->create();
        $outsider = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $workspace = Workspace::query()->create([
            'name' => 'Shared avatar workspace',
            'slug' => 'shared-avatar-workspace',
            'owner_id' => $owner->id,
        ]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($peer->id, ['role' => 'member']);
        app(WorkspaceContext::class)->activate($owner, $workspace);

        $this->actingAs($owner)
            ->patch(route('profile.update'), [
                'name' => $owner->name,
                'email' => $owner->email,
                'avatar' => UploadedFile::fake()->image('avatar.png', 120, 120),
            ])
            ->assertSessionHasNoErrors();

        $owner->refresh();
        $media = ContentMedia::query()->findOrFail($owner->avatar_media_id);
        $this->assertSame(MediaPurpose::AccountAvatar, $media->purpose);
        $this->assertNull($media->expires_at);
        $this->assertSame($owner->personalWorkspace->getKey(), $media->workspace_id);

        $route = route('account.avatar', $media->public_id);
        $this->actingAs($owner)->get($route)->assertOk();
        $this->actingAs($peer)->get($route)->assertOk();
        $this->actingAs($admin)->get($route)->assertOk();
        $this->actingAs($outsider)->get($route)->assertForbidden();
    }

    public function test_avatar_removal_soft_deletes_media_and_clears_reference(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.png', 80, 80),
        ])->assertSessionHasNoErrors();

        $mediaId = $user->refresh()->avatar_media_id;
        $this->assertNotNull($mediaId);

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'remove_avatar' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar_media_id);
        $this->assertSoftDeleted('content_media', ['id' => $mediaId]);
    }

    public function test_google_routes_are_unavailable_without_complete_configuration(): void
    {
        config([
            'linkpilot.auth.google_enabled' => false,
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $this->get(route('google.redirect'))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('google.connect'))->assertNotFound();
    }

    public function test_google_login_uses_a_linked_identity_and_never_auto_links_an_existing_email(): void
    {
        $this->enableGoogle();
        $linked = User::factory()->create();
        $linked->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-linked',
            'email' => $linked->email,
            'email_verified' => true,
            'linked_at' => now(),
        ]);

        $this->mockGoogleUser('google-linked', $linked->email);

        $this->get(route('google.callback'))->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($linked->refresh());

        auth()->logout();

        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $this->mockGoogleUser('google-new', $existing->email);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'google-new']);
        $this->assertNull($existing->refresh()->socialAccounts()->first());
    }

    public function test_google_login_denies_suspended_and_pending_deletion_accounts(): void
    {
        $this->enableGoogle();

        foreach ([['is_suspended' => true, 'deletion_requested_at' => null], ['is_suspended' => false, 'deletion_requested_at' => now()]] as $state) {
            $user = User::factory()->create($state);
            $user->socialAccounts()->create([
                'provider' => 'google',
                'provider_user_id' => 'google-blocked-'.$user->id,
                'email' => $user->email,
                'email_verified' => true,
                'linked_at' => now(),
            ]);

            $this->mockGoogleUser('google-blocked-'.$user->id, $user->email);

            $this->get(route('google.callback'))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('google');

            $this->assertGuest();
        }
    }

    public function test_google_connect_links_only_the_authenticated_account_and_rejects_takeover(): void
    {
        $this->enableGoogle();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        SocialAccount::query()->create([
            'user_id' => $other->id,
            'provider' => 'google',
            'provider_user_id' => 'google-owned-elsewhere',
            'email' => $other->email,
            'email_verified' => true,
            'linked_at' => now(),
        ]);

        $this->mockGoogleUser('google-owned-elsewhere', $other->email);

        $this->actingAs($owner)
            ->get(route('google.connect.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('google');

        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $owner->id,
            'provider' => 'google',
        ]);

        $this->mockGoogleUser('google-owner', $owner->email);

        $this->actingAs($owner)
            ->get(route('google.connect.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $owner->id,
            'provider' => 'google',
            'provider_user_id' => 'google-owner',
        ]);
    }

    public function test_other_sessions_can_be_revoked_without_signing_out_the_current_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->app['session']->setId('known-current-session');
        $currentId = 'known-current-session';

        foreach ([$currentId, 'other-session-id'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120 Safari/537.36',
                'payload' => base64_encode(serialize([])),
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('sessions.destroy-others'))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_api_tokens_can_be_revoked_with_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->createWorkspaceApiToken($user->currentWorkspace, 'Test token', ['links:read']);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('api-tokens.revoke'))
            ->assertRedirect();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_last_seen_updates_at_most_once_per_five_minutes(): void
    {
        Cache::flush();
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('profile.edit'))->assertOk();
        $firstSeen = $user->refresh()->last_seen_at;
        $this->assertNotNull($firstSeen);

        $this->travel(1)->minutes();
        $this->get(route('profile.edit'))->assertOk();
        $this->assertTrue($firstSeen->equalTo($user->refresh()->last_seen_at));

        $this->travel(5)->minutes();
        $this->get(route('profile.edit'))->assertOk();
        $this->assertTrue($user->refresh()->last_seen_at->greaterThan($firstSeen));
    }

    private function enableGoogle(): void
    {
        config([
            'linkpilot.auth.google_enabled' => true,
            'services.google.client_id' => 'google-client-id',
            'services.google.client_secret' => 'google-client-secret',
        ]);
    }

    private function mockGoogleUser(string $id, string $email): void
    {
        $socialiteUser = new SocialiteUser;
        $socialiteUser->id = $id;
        $socialiteUser->name = 'Google Person';
        $socialiteUser->email = $email;
        $socialiteUser->user = ['email_verified' => true];

        $driver = Mockery::mock(SocialiteProvider::class);
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        $factory = Mockery::mock(SocialiteFactory::class);
        $factory->shouldReceive('driver')->with('google')->andReturn($driver);

        Facade::clearResolvedInstance(SocialiteFactory::class);
        $this->app->instance(SocialiteFactory::class, $factory);
    }
}
