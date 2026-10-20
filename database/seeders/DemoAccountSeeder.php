<?php

namespace Database\Seeders;

use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\Plan;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Analytics\TrackingConsent;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Create the local accounts used for manual testing and browser smoke runs.
 * Safe to re-run: accounts are created once and existing accounts are left
 * untouched, so seeded content never accumulates duplicates.
 */
class DemoAccountSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const ADMIN_EMAIL = 'admin@linkpilot.test';

    public const DEMO_EMAIL = 'demo@linkpilot.test';

    public function run(): void
    {
        $this->createUser(self::ADMIN_EMAIL, 'LinkPilot Admin', 'pro', administrator: true);

        $demo = $this->createUser(self::DEMO_EMAIL, 'Demo Owner', 'pro');

        if ($demo === null) {
            return;
        }

        $this->seedDemoContent($demo);
    }

    private function createUser(string $email, string $name, string $plan, bool $administrator = false): ?User
    {
        $existing = User::query()->where('email', $email)->first();

        $user = $existing ?? User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        // The documented credentials must keep working, so converge the
        // account state on every run; only content stays first-run.
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_admin' => $administrator,
        ])->save();

        $user->subscription()->update([
            'plan_id' => Plan::query()->where('code', $plan)->sole()->id,
            'status' => 'active',
            'source' => 'manual',
        ]);

        $this->command?->info(
            ($existing === null ? 'Created' : 'Updated')
            ." [{$email}] with the {$plan} plan and password [".self::PASSWORD.'].'
        );

        return $existing === null ? $user : null;
    }

    private function seedDemoContent(User $user): void
    {
        $folder = Folder::factory()->for($user)->create(['name' => 'Launch campaign']);

        $overlay = $user->linkOverlays()->create([
            'name' => 'Launch offer',
            'heading' => 'Before you go',
            'body' => 'Check out the launch bundle first.',
            'cta_label' => 'See the launch',
            'is_active' => true,
        ]);

        $link = $user->links()->create([
            'folder_id' => $folder->id,
            'link_overlay_id' => $overlay->id,
            'destination_url' => 'https://example.com/launch',
            'title' => 'Launch announcement',
            'slug' => 'demo-launch',
            'is_active' => true,
        ]);

        $user->links()->create([
            'destination_url' => 'https://example.com/legacy',
            'title' => 'Retired campaign',
            'slug' => 'demo-retired',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $user->customDomains()->create([
            'hostname' => 'links.demo-linkpilot.test',
            'verification_token' => CustomDomain::generateVerificationToken(),
            'status' => 'pending_dns',
        ]);

        $pixel = $user->trackingPixels()->create([
            'name' => 'Demo Meta pixel',
            'provider' => 'meta_pixel',
            'provider_id' => '123456789012345',
            'is_active' => true,
        ]);

        $bioPage = BioPage::factory()->for($user)->create([
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

        $linkPage = LinkPage::query()->create([
            'user_id' => $user->id,
            'name' => 'Demo destinations',
            'slug' => 'demo-page',
            'title' => 'Everywhere demo',
            'description' => 'A published link page for manual testing.',
            'is_published' => true,
        ]);

        $linkPage->trackingPixels()->sync([$pixel->id]);

        $item = $linkPage->items()->create([
            'title' => 'Landing page',
            'destination_url' => 'https://example.com/landing',
            'position' => 0,
            'is_active' => true,
        ]);

        $webhook = new Webhook([
            'name' => 'Demo hook',
            'url' => 'https://example.com/hook',
            'events' => ['link.clicked', 'bio_page.clicked'],
            'revision' => 1,
            'is_active' => true,
        ]);
        $webhook->user_id = $user->id;
        $webhook->secret = Webhook::generateSecret();
        $webhook->save();

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
