<?php

namespace Tests\Feature\Settings;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Accounts\PurgeAccount;
use App\Services\Accounts\RestoreAccount;
use App\Services\Accounts\ScheduleAccountDeletion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountDeletionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_revokes_credentials_keeps_data_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $link = Link::factory()->for($user)->create(['workspace_id' => $workspace->id]);
        DB::table('sessions')->insert([
            'id' => 'scheduled-session',
            'user_id' => $user->id,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);
        $user->createWorkspaceApiToken($workspace, 'Scheduled token', ['links:read']);

        $service = app(ScheduleAccountDeletion::class);
        $service->schedule($user);
        $user->refresh();

        $this->assertNotNull($user->deletion_requested_at);
        $this->assertTrue($user->purge_after->between(now()->addDays(29), now()->addDays(31)));
        $this->assertDatabaseMissing('sessions', ['id' => 'scheduled-session']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('links', ['id' => $link->id]);
        $this->assertNotNull($user->personalWorkspace);

        $firstPurgeAfter = $user->purge_after;
        $service->schedule($user->fresh());
        $this->assertTrue($firstPurgeAfter->equalTo($user->fresh()->purge_after));
    }

    public function test_pending_deletion_authentication_is_limited_to_restore(): void
    {
        $user = User::factory()->create();
        app(ScheduleAccountDeletion::class)->schedule($user);

        $this->actingAs($user->fresh())
            ->get('/dashboard/links')
            ->assertRedirect(route('account.restore'));

        $this->actingAs($user->fresh())
            ->get(route('account.restore'))
            ->assertOk();

        $this->actingAs($user->fresh())
            ->post(route('account.restore.store'), ['password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNull($user->deletion_requested_at);
        $this->assertNull($user->purge_after);

        $this->actingAs($user)->get('/dashboard/links')->assertOk();
    }

    public function test_purge_waits_for_the_thirty_day_boundary(): void
    {
        $user = User::factory()->create();
        app(ScheduleAccountDeletion::class)->schedule($user);

        $this->travel(29)->days();
        $this->artisan('linkpilot:purge-deleted-accounts')->assertSuccessful();
        $this->assertNotNull($user->fresh());

        $this->travel(1)->days();
        $this->artisan('linkpilot:purge-deleted-accounts')->assertSuccessful();
        $this->assertNull($user->fresh());
    }

    public function test_purge_deletes_solo_workspaces_and_preserves_shared_content(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $teamWorkspace = $this->teamWorkspace($owner);
        $teamWorkspace->members()->attach($member->id, ['role' => 'member']);
        $sharedLink = Link::factory()->for($member)->create(['workspace_id' => $teamWorkspace->id]);

        $personalWorkspace = $member->personalWorkspace()->sole();
        $personalLink = Link::factory()->for($member)->create(['workspace_id' => $personalWorkspace->id]);
        $media = ContentMedia::query()->create([
            'workspace_id' => $personalWorkspace->id,
            'uploaded_by' => $member->id,
            'public_id' => '00000000-0000-4000-8000-000000000777',
            'disk' => 'local',
            'path' => 'test/personal.png',
            'original_name' => 'personal.png',
            'mime_type' => 'image/png',
            'byte_size' => 10,
            'sha256' => str_repeat('c', 64),
            'visibility' => 'private',
            'scan_state' => 'not_required',
            'purpose' => MediaPurpose::Image,
            'expires_at' => now()->addDay(),
        ]);

        app(ScheduleAccountDeletion::class)->schedule($member);
        $this->travel(30)->days();
        app(PurgeAccount::class)->purge($member->fresh());

        $this->assertNull($member->fresh());
        $this->assertNull($personalWorkspace->fresh());
        $this->assertDatabaseMissing('links', ['id' => $personalLink->id]);
        $this->assertDatabaseMissing('content_media', ['id' => $media->id]);
        $this->assertDatabaseHas('links', [
            'id' => $sharedLink->id,
            'workspace_id' => $teamWorkspace->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $teamWorkspace->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_safeguards_block_scheduling_and_purging(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        try {
            app(ScheduleAccountDeletion::class)->schedule($owner);
            $this->fail('Expected the collaborative owner to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('workspaces', $exception->errors());
        }

        $subscriber = User::factory()->create();
        DB::table('subscriptions')->insert([
            'workspace_id' => $subscriber->personalWorkspace()->sole()->id,
            'type' => 'default',
            'stripe_id' => 'sub_scheduled_guard',
            'stripe_status' => 'past_due',
            'stripe_price' => 'price_pro',
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(ScheduleAccountDeletion::class)->schedule($subscriber);
            $this->fail('Expected an unpaid non-ended subscription to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subscription', $exception->errors());
        }

        $admin = User::factory()->create(['is_admin' => true]);

        try {
            app(ScheduleAccountDeletion::class)->schedule($admin);
            $this->fail('Expected an administrator to be blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        }

        $owner->forceFill(['deletion_requested_at' => now(), 'purge_after' => now()->subDay()])->save();
        $this->assertFalse(app(PurgeAccount::class)->purge($owner->fresh()));
        $this->assertNotNull($owner->fresh());
    }

    public function test_restore_prevents_a_later_purge(): void
    {
        $user = User::factory()->create();
        app(ScheduleAccountDeletion::class)->schedule($user);
        app(RestoreAccount::class)->restore($user->fresh());

        $this->travel(31)->days();

        $this->assertFalse(app(PurgeAccount::class)->purge($user->fresh()));
        $this->assertNotNull($user->fresh());
    }

    private function teamWorkspace(User $owner): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => 'Deletion lifecycle team',
            'slug' => 'deletion-lifecycle-team-'.$owner->id,
            'owner_id' => $owner->id,
        ]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        return $workspace;
    }
}
