<?php

namespace Tests\Feature\Notifications;

use App\Jobs\DeliverWebhook;
use App\Models\CustomDomain;
use App\Models\Plan;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;
use App\Notifications\AccountNotification;
use App\Services\Billing\EnforcesPlanLimits;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Domains\DnsTxtLookup;
use App\Services\Domains\DomainVerificationService;
use App\Services\Domains\HttpsDomainChallengeProbe;
use App\Services\Webhooks\WebhookEndpointValidator;
use App\Services\Workspaces\WorkspaceContext;
use App\Services\Workspaces\WorkspaceInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_an_empty_notification_center(): void
    {
        $user = User::factory()->create();
        $this->withoutVite();

        $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->where('filter', 'all')
                ->has('notifications', 0)
                ->where('unreadCount', 0));
    }

    public function test_database_notifications_filter_and_mark_one_or_all_read_without_mail(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();
        $first = new AccountNotification('billing', 'Plan changed', 'The workspace plan changed.', $workspace->id, 'workspace', $workspace->id);
        $second = new AccountNotification('admin', 'Maintenance', 'Scheduled maintenance begins soon.');
        $user->notify($first);
        $user->notify($second);
        $this->withoutVite();

        $this->actingAs($user)
            ->get('/notifications?filter=unread')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->where('filter', 'unread')
                ->where('unreadCount', 2)
                ->has('notifications', 2));

        $firstRecord = $user->notifications()->where('data->title', 'Plan changed')->firstOrFail();
        $this->actingAs($user)
            ->post("/notifications/{$firstRecord->id}/read")
            ->assertRedirect();

        $this->assertNotNull($firstRecord->fresh()->read_at);
        $this->actingAs($user)
            ->get('/notifications?filter=unread')
            ->assertInertia(fn (Assert $page) => $page
                ->where('unreadCount', 1)
                ->has('notifications', 1));

        $this->actingAs($user)
            ->post('/notifications/read-all')
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(2, $user->notifications()->count());
    }

    public function test_shared_unread_count_refreshes_on_inertia_navigation(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create();
        $user->notify(new AccountNotification('admin', 'Notice', 'A platform update.'));
        $this->withoutVite();

        $this->actingAs($user)
            ->get('/dashboard/links')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('notificationUnreadCount', 1)
                ->has('notificationPreview', 1)
                ->where('notificationPreview.0.title', 'Notice'));

        $record = $user->notifications()->firstOrFail();
        $this->actingAs($user)->post("/notifications/{$record->id}/read")->assertRedirect();

        $this->actingAs($user)
            ->get('/dashboard/links')
            ->assertInertia(fn (Assert $page) => $page
                ->where('notificationUnreadCount', 0)
                ->has('notificationPreview', 1)
                ->where('notificationPreview.0.readAt', $record->fresh()->read_at?->toIso8601String()));
    }

    public function test_email_channel_requires_a_user_preference_and_configured_mailer(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->sole();

        $user->notify(new AccountNotification('webhooks', 'No preference', 'A delivery failed.', $workspace->id, 'workspace', $workspace->id));
        $this->assertNotificationChannels($user, 'No preference', ['database']);

        $this->actingAs($user)
            ->patch('/notifications/preferences', ['emailPreferences' => ['webhooks' => true]])
            ->assertRedirect();

        config(['mail.default' => 'log']);
        $user->notify(new AccountNotification('webhooks', 'No mailer', 'A delivery failed.', $workspace->id, 'workspace', $workspace->id));
        $this->assertNotificationChannels($user, 'No mailer', ['database']);

        config(['mail.default' => 'smtp']);
        $user->notify(new AccountNotification('webhooks', 'Preference and mailer', 'A delivery failed.', $workspace->id, 'workspace', $workspace->id));
        $this->assertNotificationChannels($user, 'Preference and mailer', ['database', 'mail']);
    }

    public function test_saving_notification_preferences_flashes_a_success_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/notifications/preferences', ['emailPreferences' => ['webhooks' => true]])
            ->assertRedirect()
            ->assertInertiaFlash('toast', [
                'type' => 'success',
                'message' => 'Notification preferences updated.',
            ]);
    }

    public function test_notification_content_is_hidden_after_target_deletion_or_workspace_access_loss(): void
    {
        config(['mail.default' => 'log']);
        $owner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->sole();
        $webhook = $this->webhookFor($owner, $workspace);
        $owner->notify(new AccountNotification('webhooks', 'Deleted endpoint alert', 'Endpoint details are private.', $workspace->id, 'webhook', $webhook->id));
        $webhook->delete();
        $this->withoutVite();

        $this->actingAs($owner)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications', 0)
                ->where('unreadCount', 0)
                ->where('notificationUnreadCount', 0)
                ->has('notificationPreview', 0))
            ->assertDontSee('Deleted endpoint alert')
            ->assertDontSee('Endpoint details are private.');

        $member = User::factory()->create();
        $workspace->members()->attach($member, ['role' => 'admin']);
        app(WorkspaceContext::class)->activate($member, $workspace);
        $member->notify(new AccountNotification('billing', 'Removed workspace alert', 'Former workspace details are private.', $workspace->id, 'workspace', $workspace->id));
        $workspace->members()->detach($member->id);

        $this->actingAs($member)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications', 0)
                ->where('unreadCount', 0)
                ->where('notificationUnreadCount', 0)
                ->has('notificationPreview', 0))
            ->assertDontSee('Removed workspace alert')
            ->assertDontSee('Former workspace details are private.');
    }

    public function test_terminal_domain_and_webhook_failures_notify_workspace_managers_without_endpoint_data(): void
    {
        config([
            'mail.default' => 'log',
            'services.linkpilot.webhooks.max_attempts' => 1,
        ]);
        $owner = User::factory()->create();
        $workspace = $owner->personalWorkspace()->sole();
        $domain = $workspace->customDomains()->create([
            'user_id' => $owner->id,
            'hostname' => 'failed.example.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'active',
        ]);
        $this->app->instance(DnsTxtLookup::class, new class extends DnsTxtLookup
        {
            public function recordsFor(string $hostname): array
            {
                return [];
            }
        });
        $this->app->instance(HttpsDomainChallengeProbe::class, new class extends HttpsDomainChallengeProbe
        {
            public function respondsWithToken(string $hostname, string $token): bool
            {
                return false;
            }
        });

        app(DomainVerificationService::class)->verify($domain);
        $this->assertSame('failed', $domain->fresh()->status);
        $this->assertSame('domains', $this->notificationData($owner, 'domains')['category']);

        $webhook = $this->webhookFor($owner, $workspace);
        $delivery = WebhookDelivery::query()->create([
            'webhook_id' => $webhook->id,
            'revision' => $webhook->revision,
            'event_uuid' => (string) Str::uuid(),
            'event_name' => 'link.clicked',
            'body' => '{"event":"link.clicked"}',
            'attempt_count' => 0,
            'state' => 'pending',
        ]);
        $this->app->instance(WebhookEndpointValidator::class, new class extends WebhookEndpointValidator
        {
            protected function resolveAddresses(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        Http::fake(fn () => Http::response('private receiver response', 500));

        $this->app->call([new DeliverWebhook($delivery->id), 'handle']);

        $this->assertSame('failed', $delivery->fresh()->state);
        $data = $this->notificationData($owner, 'webhooks');
        $serialized = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($webhook->url, $serialized);
        $this->assertStringNotContainsString('webhook-secret', $serialized);
        $this->assertStringNotContainsString('private receiver response', $serialized);
    }

    public function test_reaching_a_plan_limit_and_changing_a_plan_notify_workspace_managers(): void
    {
        config(['mail.default' => 'log']);
        $usageOwner = User::factory()->create();
        $usageWorkspace = $usageOwner->personalWorkspace()->sole();
        $freePlan = Plan::query()->where('code', 'free')->sole();
        DB::table('plans')->where('id', $freePlan->id)->update(['link_limit' => 1]);

        app(EnforcesPlanLimits::class)->create($usageWorkspace, 'links', function (Workspace $lockedWorkspace) use ($usageOwner) {
            return $lockedWorkspace->links()->create([
                'user_id' => $usageOwner->id,
                'slug' => 'limit-notification',
                'destination_url' => 'https://example.test/limit',
                'is_active' => true,
            ]);
        });

        $this->assertSame('usage', $this->notificationData($usageOwner, 'usage')['category']);

        $memberOwner = User::factory()->create();
        $memberWorkspace = $memberOwner->personalWorkspace()->sole();
        DB::table('plans')->where('id', $freePlan->id)->update(['member_limit' => 2]);
        $this->actingAs($memberOwner);
        app(WorkspaceInvitationService::class)->issue(
            $memberOwner,
            $memberWorkspace,
            'pending-member@example.test',
            'member',
        );

        $this->assertSame('usage', $this->notificationData($memberOwner, 'usage')['category']);

        $billingOwner = User::factory()->create();
        $billingWorkspace = $billingOwner->personalWorkspace()->sole();
        $proPlan = Plan::query()->where('code', 'pro')->sole();
        app(WorkspaceUsage::class)->assignPlan($billingWorkspace, $proPlan);

        $billingData = $this->notificationData($billingOwner, 'billing');
        $this->assertSame('billing', $billingData['category']);
        $this->assertSame($billingWorkspace->id, $billingData['workspace_id']);
    }

    public function test_accepting_a_workspace_invitation_notifies_its_managers(): void
    {
        config(['mail.default' => 'log']);
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'new-member@example.test']);
        $workspace = $owner->personalWorkspace()->sole();
        $workspace->members()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
        $proPlan = Plan::query()->where('code', 'pro')->sole();
        app(WorkspaceUsage::class)->assignPlan($workspace, $proPlan);
        $invitations = app(WorkspaceInvitationService::class);
        $this->actingAs($owner);
        $issued = $invitations->issue($owner, $workspace, $invitee->email, 'member');

        $invitations->accept($invitee, $issued['token']);

        $data = $this->notificationData($owner, 'invitations');
        $this->assertSame('invitations', $data['category']);
        $this->assertSame($workspace->id, $data['workspace_id']);
        $this->assertArrayNotHasKey('token', $data);
    }

    public function test_platform_admin_broadcasts_to_active_accounts_only(): void
    {
        config(['mail.default' => 'log']);
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $suspended = User::factory()->create(['is_suspended' => true]);

        $this->actingAs($admin)
            ->post('/admin/notifications/broadcast', [
                'title' => 'Scheduled maintenance',
                'body' => 'The service will be unavailable briefly.',
            ])
            ->assertRedirect('/admin');

        $this->assertSame('admin', $this->notificationData($user, 'admin')['category']);
        $this->assertSame('admin', $this->notificationData($admin, 'admin')['category']);
        $this->assertSame(0, $suspended->notifications()->count());

        $this->actingAs($user)
            ->post('/admin/notifications/broadcast', [
                'title' => 'Unauthorized broadcast',
                'body' => 'This must not be delivered.',
            ])
            ->assertForbidden();
        $this->assertDatabaseMissing('notifications', ['data->title' => 'Unauthorized broadcast']);
    }

    private function assertNotificationChannels(User $user, string $title, array $expected): void
    {
        Notification::assertSentTo(
            $user,
            AccountNotification::class,
            fn (AccountNotification $notification, array $channels): bool => $notification->title === $title && $channels === $expected,
        );
    }

    private function notificationData(User $user, string $category): array
    {
        $notification = $user->notifications()->get()->first(
            fn ($notification): bool => ($notification->data['category'] ?? null) === $category,
        );

        $this->assertNotNull($notification, "Expected a {$category} notification for user {$user->id}.");

        return $notification->data;
    }

    private function webhookFor(User $user, Workspace $workspace): Webhook
    {
        $webhook = $workspace->webhooks()->make([
            'name' => 'Failure hook',
            'url' => 'https://hooks.example.test/hook',
            'events' => ['link.clicked'],
            'revision' => 1,
            'is_active' => true,
        ]);
        $webhook->forceFill([
            'user_id' => $user->id,
            'secret' => 'webhook-secret',
        ])->save();

        return $webhook;
    }
}
