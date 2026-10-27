<?php

namespace Database\Seeders;

use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Plan;
use App\Models\TrackingPixelRule;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Create the local accounts used for manual testing and browser smoke runs.
 * Safe to re-run: account credentials and plans converge on every run while
 * content is seeded once, so repeated runs never duplicate records.
 */
class DemoAccountSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const ADMIN_EMAIL = 'admin@linkpilot.test';

    public const USER_EMAIL = 'user@linkpilot.test';

    public function run(): void
    {
        // Administrator access is granted through `linkpilot:grant-admin`, not
        // here, so the provisioning path stays the only source of the flag.
        $this->convergeUser(self::ADMIN_EMAIL, 'LinkPilot Admin', 'pro');

        $user = $this->convergeUser(self::USER_EMAIL, 'Demo Owner', 'pro');

        if ($user !== null) {
            $this->seedDemoContent($user);
        }
    }

    private function convergeUser(string $email, string $name, string $plan): ?User
    {
        $existing = User::query()->where('email', $email)->first();

        $user = $existing ?? User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        app(WorkspaceUsage::class)->assignPlan(
            $user->personalWorkspace()->firstOrFail(),
            Plan::query()->where('code', $plan)->sole(),
        );

        $this->command?->info(
            ($existing === null ? 'Created' : 'Updated')." [{$email}] on the {$plan} plan."
        );

        return $existing === null ? $user : null;
    }

    private function seedDemoContent(User $user): void
    {
        $workspace = $user->personalWorkspace()->firstOrFail();
        $folder = Folder::factory()->for($user)->create([
            'workspace_id' => $workspace->id,
            'name' => 'Launch campaign',
        ]);

        $overlay = $workspace->linkOverlays()->create([
            'user_id' => $user->id,
            'name' => 'Launch offer',
            'heading' => 'Before you go',
            'body' => 'Check out the launch bundle first.',
            'cta_label' => 'See the launch',
            'is_active' => true,
        ]);

        $link = $workspace->links()->create([
            'user_id' => $user->id,
            'folder_id' => $folder->id,
            'link_overlay_id' => $overlay->id,
            'destination_url' => 'https://example.com/launch',
            'title' => 'Launch announcement',
            'slug' => 'demo-launch',
            'is_active' => true,
        ]);

        $workspace->links()->create([
            'user_id' => $user->id,
            'destination_url' => 'https://example.com/legacy',
            'title' => 'Retired campaign',
            'slug' => 'demo-retired',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $workspace->customDomains()->create([
            'user_id' => $user->id,
            'hostname' => 'links.demo-linkpilot.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'pending_dns',
        ]);

        $pixel = $workspace->trackingPixels()->create([
            'user_id' => $user->id,
            'name' => 'Demo Meta pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '123456789012345',
            'is_active' => true,
        ]);

        $bioPage = BioPage::factory()->for($user)->create([
            'workspace_id' => $workspace->id,
            'handle' => 'demo-bio',
            'display_name' => 'Demo Creator',
            'bio' => 'Manual-testing bio page.',
            'is_published' => true,
        ]);

        $bioPage->bioLinks()->createMany([
            [
                'title' => 'Website',
                'destination_url' => 'https://example.com',
                'position' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'Newsletter',
                'destination_url' => 'https://example.com/newsletter',
                'position' => 1,
                'is_active' => true,
            ],
        ]);

        $linkPage = $workspace->linkPages()->create([
            'user_id' => $user->id,
            'name' => 'Demo destinations',
            'slug' => 'demo-page',
            'title' => 'Everywhere demo',
            'description' => 'A published link page for manual testing.',
            'is_published' => true,
        ]);

        $pixel->rules()->create([
            'workspace_id' => $workspace->id,
            'target_type' => TrackingPixelRule::TARGET_LINK_PAGE,
            'target_id' => $linkPage->id,
            'trigger' => TrackingPixelRule::TRIGGER_PAGE_VIEW,
        ]);

        $item = $linkPage->items()->create([
            'title' => 'Landing page',
            'destination_url' => 'https://example.com/landing',
            'position' => 0,
            'is_active' => true,
        ]);

        $webhook = $workspace->webhooks()->make([
            'name' => 'Demo hook',
            'url' => 'https://example.com/hook',
            'events' => ['link.clicked', 'bio_page.clicked'],
            'revision' => 1,
            'is_active' => true,
        ]);
        $webhook->forceFill([
            'user_id' => $user->id,
            'secret' => Webhook::generateSecret(),
        ])->save();

        $this->seedActivity($user, $link, $linkPage, $item, $bioPage);
    }

    private function seedActivity(User $user, Link $link, LinkPage $linkPage, LinkPageItem $item, BioPage $bioPage): void
    {
        foreach ([6, 5, 4, 3, 2, 1, 0] as $daysAgo) {
            foreach (range(1, 2) as $index) {
                $link->clicks()->create([
                    'occurred_at' => now()->subDays($daysAgo)->setTime(9 + $index, 15),
                ]);
            }
        }

        $tracker = app(AnalyticsTracker::class);
        $request = Request::create('/', 'GET');
        $request->cookies->set(TrackingConsent::COOKIE_NAME, TrackingConsent::GRANTED);

        foreach (['link.clicked', 'link_page.view', 'bio_page.view'] as $eventName) {
            $tracker->record($request, $eventName, $user, [
                'link' => $link,
                'linkPage' => $linkPage,
                'linkPageItem' => $item,
                'bioPage' => $bioPage,
            ]);
        }

        $this->command?->info('Seeded demo links, domains, pixels, pages, an overlay, a webhook, and click history.');
    }
}
