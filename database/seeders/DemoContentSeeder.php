<?php

namespace Database\Seeders;

use App\Models\AnalyticsEvent;
use App\Models\BioPage;
use App\Models\CustomDomain;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\User;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\CreatesPersonalWorkspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seed realistic demo data so the user and admin areas have something to show:
 * accounts in different lifecycle states, routes with tags and click history,
 * bio pages, link pages, domains, QR campaigns, pixels, and webhooks.
 *
 * Safe to re-run: records converge on their natural keys and activity is only
 * written when a route has none yet.
 */
class DemoContentSeeder extends Seeder
{
    private const CLICK_WINDOW_DAYS = 30;

    /**
     * @return array<string, User>
     */
    public function run(): array
    {
        $users = $this->seedAccounts();
        $owner = $users[DemoAccountSeeder::USER_EMAIL];
        $workspace = $this->personalWorkspace($owner);

        $content = $this->seedOwnerContent($owner, $workspace);
        $this->seedCollaboratorContent($users);
        $this->seedActivity($owner, $workspace, $content);

        $this->command?->info('Seeded demo accounts, routes, pages, domains, QR campaigns, and activity.');

        return $users;
    }

    /**
     * @return array<string, User>
     */
    private function seedAccounts(): array
    {
        $specs = [
            ['name' => 'Nina Petrova', 'email' => 'nina@brightlab.test', 'plan' => 'pro', 'verified' => true],
            ['name' => 'Marcus Webb', 'email' => 'marcus@studioarc.test', 'plan' => 'business', 'verified' => true, 'role' => 'support'],
            ['name' => 'Aisha Rahman', 'email' => 'aisha@northbeam.test', 'plan' => 'free', 'verified' => true, 'suspended' => true],
            ['name' => 'Tomas Alvarez', 'email' => 'tomas@fieldnotes.test', 'plan' => 'free', 'verified' => false],
            ['name' => 'Priya Nair', 'email' => 'priya@driftline.test', 'plan' => 'pro', 'verified' => true],
            ['name' => 'Jonah Kim', 'email' => 'jonah@harborline.test', 'plan' => 'free', 'verified' => true, 'deletion_pending' => true],
        ];

        $owner = User::query()->where('email', DemoAccountSeeder::USER_EMAIL)->firstOrFail();
        $users = [$owner->email => $owner];

        foreach ($specs as $spec) {
            $user = User::query()->firstOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'password' => Hash::make(DemoAccountSeeder::PASSWORD),
                    'email_verified_at' => $spec['verified']
                        ? now()->subDays(30)
                        : null,
                ],
            );

            $user->forceFill([
                'name' => $spec['name'],
                'password' => Hash::make(DemoAccountSeeder::PASSWORD),
                'email_verified_at' => $spec['verified']
                    ? ($user->email_verified_at ?? now()->subDays(30))
                    : null,
                'is_suspended' => (bool) ($spec['suspended'] ?? false),
                'suspended_at' => ($spec['suspended'] ?? false) ? now()->subDays(3) : null,
                'deletion_requested_at' => ($spec['deletion_pending'] ?? false) ? now()->subDays(2) : null,
                'purge_after' => ($spec['deletion_pending'] ?? false) ? now()->addDays(28) : null,
                'last_seen_at' => now()->subHours((int) random_int(1, 72)),
            ])->save();

            $workspace = $this->personalWorkspace($user);
            app(WorkspaceUsage::class)->assignPlan(
                $workspace,
                Plan::query()->where('code', $spec['plan'])->sole(),
            );

            if (isset($spec['role'])) {
                $role = PlatformRole::query()
                    ->where('code', $spec['role'])
                    ->sole();

                $user->platformRoles()->syncWithoutDetaching([$role->id]);
            }

            $users[$user->email] = $user;
        }

        return $users;
    }

    private function personalWorkspace(User $user): Workspace
    {
        return app(CreatesPersonalWorkspace::class)->for($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedOwnerContent(User $owner, Workspace $workspace): array
    {
        $folders = collect([
            'launch' => 'Launch campaign',
            'partners' => 'Partnerships',
            'content' => 'Content',
        ])->mapWithKeys(fn (string $name, string $key) => [
            $key => $workspace->folders()->updateOrCreate(
                ['name' => $name],
                ['user_id' => $owner->id],
            ),
        ])->all();

        $tags = collect([
            ['name' => 'Launch', 'slug' => 'launch', 'color' => '#2563eb'],
            ['name' => 'Newsletter', 'slug' => 'newsletter', 'color' => '#0f766e'],
            ['name' => 'Partner', 'slug' => 'partner', 'color' => '#b45309'],
            ['name' => 'Content', 'slug' => 'content', 'color' => '#7b8794'],
        ])->mapWithKeys(fn (array $tag) => [
            $tag['slug'] => $workspace->linkTags()->updateOrCreate(
                ['slug' => $tag['slug']],
                ['name' => $tag['name'], 'color' => $tag['color']],
            ),
        ]);

        $domains = [
            'short.demo-linkpilot.test' => $workspace->customDomains()->updateOrCreate(
                ['hostname' => 'short.demo-linkpilot.test'],
                [
                    'user_id' => $owner->id,
                    'verification_token' => CustomDomain::generateVerificationToken(),
                    'status' => 'active',
                    'verified_at' => now()->subDays(21),
                ],
            ),
            'try.demo-linkpilot.test' => $workspace->customDomains()->updateOrCreate(
                ['hostname' => 'try.demo-linkpilot.test'],
                [
                    'user_id' => $owner->id,
                    'verification_token' => CustomDomain::generateVerificationToken(),
                    'status' => 'failed',
                ],
            ),
        ];

        $overlay = $workspace->linkOverlays()->updateOrCreate(
            ['name' => 'Newsletter prompt'],
            [
                'user_id' => $owner->id,
                'heading' => 'Get the monthly drop',
                'body' => 'One email a month with the campaigns and numbers behind them.',
                'cta_label' => 'Subscribe',
                'is_active' => true,
            ],
        );

        $workspace->trackingPixels()->updateOrCreate(
            ['name' => 'Demo GA4 pixel'],
            [
                'user_id' => $owner->id,
                'provider' => 'google_analytics',
                'provider_id' => 'G-4K2ND9X7QP',
                'is_active' => true,
            ],
        );

        $webhook = $workspace->webhooks()->firstOrNew(['name' => 'CRM sync']);

        if (! $webhook->exists) {
            $webhook->fill([
                'url' => 'https://api.example.test/hooks/linkpilot',
                'events' => ['link.clicked', 'link_page.view', 'bio_page.clicked'],
                'revision' => 1,
                'is_active' => true,
            ])->forceFill([
                'user_id' => $owner->id,
                'secret' => Webhook::generateSecret(),
            ])->save();
        }

        if (! $owner->tokens()->where('name', 'Zapier integration')->exists()) {
            $owner->createWorkspaceApiToken(
                $workspace,
                'Zapier integration',
                ['links:read'],
                now()->addMonths(6),
            );
        }

        $linkPage = LinkPage::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => 'launch-hub'],
            [
                'user_id' => $owner->id,
                'name' => 'Launch hub',
                'title' => 'Spring launch hub',
                'description' => 'Everything from the spring release in one place.',
                'is_published' => true,
            ],
        );

        collect([
            ['title' => 'Watch the launch keynote', 'destination_url' => 'https://example.com/keynote', 'position' => 0],
            ['title' => 'Read the release notes', 'destination_url' => 'https://example.com/notes', 'position' => 1],
            ['title' => 'Book a walkthrough', 'destination_url' => 'https://example.com/walkthrough', 'position' => 2],
        ])->each(fn (array $item) => $linkPage->items()->updateOrCreate(
            ['title' => $item['title']],
            [
                'destination_url' => $item['destination_url'],
                'position' => $item['position'],
                'is_active' => true,
            ],
        ));

        $links = collect([
            ['slug' => 'spring-tour', 'title' => 'Spring product tour', 'destination_url' => 'https://example.com/spring-tour', 'folder' => 'launch', 'tags' => ['launch'], 'clicks' => 240],
            ['slug' => 'podcast-42', 'title' => 'Podcast episode 42', 'destination_url' => 'https://example.com/podcast/42', 'folder' => 'content', 'tags' => ['content'], 'clicks' => 180],
            ['slug' => 'newsletter-signup', 'title' => 'Newsletter signup', 'destination_url' => 'https://example.com/newsletter', 'folder' => 'content', 'tags' => ['newsletter'], 'clicks' => 150],
            ['slug' => 'docs-quickstart', 'title' => 'Docs quickstart', 'destination_url' => 'https://docs.example.com/quickstart', 'folder' => null, 'tags' => [], 'clicks' => 120],
            ['slug' => 'partner-acme', 'title' => 'Partner referral — Acme', 'destination_url' => 'https://example.com/partners/acme', 'folder' => 'partners', 'tags' => ['partner', 'launch'], 'clicks' => 90],
            ['slug' => 'meetup-rsvp', 'title' => 'Community meetup RSVP', 'destination_url' => 'https://example.com/meetup', 'folder' => null, 'tags' => [], 'clicks' => 60, 'expires_at' => now()->addDays(21)],
            ['slug' => 'beta-invite', 'title' => 'Beta invite', 'destination_url' => 'https://example.com/beta', 'folder' => 'launch', 'tags' => ['launch'], 'clicks' => 45, 'access_code' => Hash::make('beta2026')],
            ['slug' => 'webinar-replay', 'title' => 'Webinar replay', 'destination_url' => 'https://example.com/webinar', 'folder' => 'content', 'tags' => ['content'], 'clicks' => 30, 'is_active' => false],
            ['slug' => 'old-pricing', 'title' => 'Old pricing page', 'destination_url' => 'https://example.com/pricing-v1', 'folder' => null, 'tags' => [], 'clicks' => 12, 'archived_at' => now()->subDays(10)],
            ['slug' => 'launch-hub', 'title' => 'Launch hub page', 'destination_url' => 'https://demo-linkpilot.test/pages/launch-hub', 'folder' => 'launch', 'tags' => ['launch'], 'clicks' => 75, 'delivery_mode' => 'link_page', 'link_page_id' => $linkPage->id],
        ])->mapWithKeys(function (array $spec) use ($owner, $workspace, $folders, $tags, $domains, $overlay) {
            $link = Link::query()->updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'user_id' => $owner->id,
                    'workspace_id' => $workspace->id,
                    'folder_id' => $spec['folder'] !== null ? $folders[$spec['folder']]->id : null,
                    'custom_domain_id' => $spec['slug'] === 'partner-acme'
                        ? $domains['short.demo-linkpilot.test']->id
                        : null,
                    'link_overlay_id' => $spec['slug'] === 'newsletter-signup' ? $overlay->id : null,
                    'delivery_mode' => $spec['delivery_mode'] ?? 'direct',
                    'link_page_id' => $spec['link_page_id'] ?? null,
                    'title' => $spec['title'],
                    'destination_url' => $spec['destination_url'],
                    'is_active' => $spec['is_active'] ?? true,
                    'access_code' => $spec['access_code'] ?? null,
                    'expires_at' => $spec['expires_at'] ?? null,
                    'archived_at' => $spec['archived_at'] ?? null,
                ],
            );

            $link->forceFill(['click_count' => $spec['clicks']])->save();

            $link->tags()->sync(
                collect($spec['tags'])->map(fn (string $slug) => $tags[$slug]->id)->all(),
            );

            return [$spec['slug'] => ['link' => $link, 'clicks' => $spec['clicks']]];
        })->all();

        QrCode::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'title' => 'Conference booth code'],
            [
                'created_by' => $owner->id,
                'source' => 'campaign',
                'destination_url' => 'https://example.com/booth',
                'is_active' => true,
                'scan_count' => 96,
                'share_title' => 'Talk to us after the talk',
                'share_description' => 'Book a follow-up with the team.',
            ],
        );

        QrCode::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'title' => 'Packaging insert'],
            [
                'created_by' => $owner->id,
                'source' => 'campaign',
                'destination_url' => 'https://example.com/register',
                'is_active' => true,
                'scan_count' => 41,
            ],
        );

        return [
            'folders' => $folders,
            'tags' => $tags,
            'domains' => $domains,
            'overlay' => $overlay,
            'webhook' => $webhook,
            'linkPage' => $linkPage,
            'links' => $links,
        ];
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedCollaboratorContent(array $users): void
    {
        $nina = $users['nina@brightlab.test'];
        $ninaWorkspace = $this->personalWorkspace($nina);

        foreach ([
            ['slug' => 'brightlab-home', 'title' => 'Brightlab home', 'destination_url' => 'https://brightlab.test'],
            ['slug' => 'brightlab-case', 'title' => 'Brightlab case study', 'destination_url' => 'https://brightlab.test/case-study'],
        ] as $spec) {
            Link::query()->updateOrCreate(
                ['slug' => $spec['slug']],
                [
                    'user_id' => $nina->id,
                    'workspace_id' => $ninaWorkspace->id,
                    'title' => $spec['title'],
                    'destination_url' => $spec['destination_url'],
                    'is_active' => true,
                    'click_count' => 64,
                ],
            );
        }

        $ninaBio = BioPage::query()->updateOrCreate(
            ['handle' => 'nina-creates'],
            [
                'user_id' => $nina->id,
                'workspace_id' => $ninaWorkspace->id,
                'display_name' => 'Nina Petrova',
                'bio' => 'Product marketing at Brightlab. I write about launches and lifecycle.',
                'is_published' => true,
            ],
        );

        collect([
            ['title' => 'My newsletter', 'destination_url' => 'https://brightlab.test/nina'],
            ['title' => 'Launch checklist', 'destination_url' => 'https://brightlab.test/checklist'],
            ['title' => 'Book a call', 'destination_url' => 'https://brightlab.test/call'],
        ])->each(fn (array $link, int $position) => $ninaBio->bioLinks()->updateOrCreate(
            ['title' => $link['title']],
            [
                'destination_url' => $link['destination_url'],
                'position' => $position,
                'is_active' => true,
            ],
        ));

        $marcus = $users['marcus@studioarc.test'];
        $marcusWorkspace = $this->personalWorkspace($marcus);

        Link::query()->updateOrCreate(
            ['slug' => 'studio-arc'],
            [
                'user_id' => $marcus->id,
                'workspace_id' => $marcusWorkspace->id,
                'title' => 'Studio Arc portfolio',
                'destination_url' => 'https://studioarc.test',
                'is_active' => true,
                'click_count' => 210,
            ],
        );

        BioPage::query()->updateOrCreate(
            ['handle' => 'studio-arc'],
            [
                'user_id' => $marcus->id,
                'workspace_id' => $marcusWorkspace->id,
                'display_name' => 'Studio Arc',
                'bio' => 'A small design studio for launch campaigns.',
                'is_published' => true,
            ],
        );

        $priya = $users['priya@driftline.test'];
        $priyaWorkspace = $this->personalWorkspace($priya);

        Link::query()->updateOrCreate(
            ['slug' => 'driftline-demo'],
            [
                'user_id' => $priya->id,
                'workspace_id' => $priyaWorkspace->id,
                'title' => 'Driftline product demo',
                'destination_url' => 'https://driftline.test/demo',
                'is_active' => true,
                'click_count' => 88,
            ],
        );

        $conference = LinkPage::query()->updateOrCreate(
            ['workspace_id' => $priyaWorkspace->id, 'slug' => 'conference-hub'],
            [
                'user_id' => $priya->id,
                'name' => 'Conference hub',
                'title' => 'Driftline at RelayConf',
                'description' => 'Talks, demos, and office hours from the conference.',
                'is_published' => false,
            ],
        );

        collect([
            ['title' => 'Watch the talk', 'destination_url' => 'https://driftline.test/talk'],
            ['title' => 'Office hours', 'destination_url' => 'https://driftline.test/office-hours'],
        ])->each(fn (array $item, int $position) => $conference->items()->updateOrCreate(
            ['title' => $item['title']],
            [
                'destination_url' => $item['destination_url'],
                'position' => $position,
                'is_active' => true,
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function seedActivity(User $owner, Workspace $workspace, array $content): void
    {
        $links = $content['links'];

        foreach ($links as $slug => $entry) {
            /** @var Link $link */
            $link = $entry['link'];

            if ($link->clicks()->exists()) {
                continue;
            }

            $total = (int) $entry['clicks'];

            for ($day = self::CLICK_WINDOW_DAYS - 1; $day >= 0; $day--) {
                $share = (int) round($total / self::CLICK_WINDOW_DAYS * (1 + 0.6 * sin(($day + crc32($slug)) / 4)));
                $share = max($day < 2 ? 0 : 1, $share);

                foreach (range(1, min($share, 12)) as $index) {
                    $link->clicks()->create([
                        'occurred_at' => now()->subDays($day)->setTime(8 + ($index % 12), ($index * 7) % 60),
                    ]);
                }
            }

            $link->forceFill(['click_count' => $link->clicks()->count()])->save();
        }

        $demoLink = $links['spring-tour']['link'];
        $linkPage = $content['linkPage'];
        $ninaBio = BioPage::query()->where('handle', 'nina-creates')->sole();

        if (! AnalyticsEvent::query()->where('event_name', 'trial_started')->exists()) {
            $browsers = ['Chrome', 'Safari', 'Firefox', 'Edge'];
            $platforms = ['macOS', 'Windows', 'iOS', 'Android'];
            $countries = ['US', 'DE', 'GB', 'FR', 'CA'];
            $referrers = ['google.com', 'news.ycombinator.com', 'x.com', 'linkedin.com', null];

            foreach (range(self::CLICK_WINDOW_DAYS - 1, 0) as $day) {
                $dayStamp = now('UTC')->subDays($day)->toDateString();

                if (
                    AnalyticsEvent::query()
                        ->where('workspace_id', $workspace->id)
                        ->where('event_name', 'link.clicked')
                        ->whereDate('occurred_at', $dayStamp)
                        ->exists()
                ) {
                    continue;
                }

                foreach (range(1, 3) as $index) {
                    $visitor = ($day * 3) + $index;

                    AnalyticsEvent::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'workspace_id' => $workspace->id,
                        'user_id' => $owner->id,
                        'link_id' => $demoLink->id,
                        'event_name' => 'link.clicked',
                        'occurred_at' => now('UTC')->subDays($day)->setTime(9 + $index, 15),
                        'visitor_hash' => hash('sha256', "visitor-{$visitor}"),
                        'visitor_key_version' => (string) (config('services.linkpilot.analytics.hash_key_version') ?: 'local'),
                        'referrer_host' => $referrers[$visitor % count($referrers)],
                        'browser' => $browsers[$visitor % count($browsers)],
                        'platform' => $platforms[$visitor % count($platforms)],
                        'country_code' => $countries[$visitor % count($countries)],
                        'utm_source' => ['newsletter', 'twitter', 'partner'][($visitor % 3)],
                        'utm_medium' => 'referral',
                        'utm_campaign' => 'spring-launch',
                    ]);
                }

                if (
                    $day % 3 === 0
                    && ! AnalyticsEvent::query()
                        ->where('workspace_id', $workspace->id)
                        ->where('event_name', 'link_page.view')
                        ->whereDate('occurred_at', $dayStamp)
                        ->exists()
                ) {
                    AnalyticsEvent::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'workspace_id' => $workspace->id,
                        'user_id' => $owner->id,
                        'link_page_id' => $linkPage->id,
                        'event_name' => 'link_page.view',
                        'occurred_at' => now('UTC')->subDays($day)->setTime(11, 5),
                        'visitor_hash' => hash('sha256', "page-visitor-{$day}"),
                        'visitor_key_version' => (string) (config('services.linkpilot.analytics.hash_key_version') ?: 'local'),
                        'browser' => 'Chrome',
                        'platform' => 'Windows',
                        'country_code' => 'US',
                    ]);
                }
            }

            $workspace->analyticsEventDefinitions()->firstOrCreate(
                ['event_name' => 'trial_started'],
                ['is_active' => true, 'created_by' => $owner->id],
            );

            foreach (range(1, 9) as $index) {
                AnalyticsEvent::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'workspace_id' => $workspace->id,
                    'user_id' => $owner->id,
                    'link_id' => $demoLink->id,
                    'event_name' => 'trial_started',
                    'occurred_at' => now('UTC')->subDays($index * 3)->setTime(14, 30),
                    'visitor_hash' => hash('sha256', "trial-visitor-{$index}"),
                    'visitor_key_version' => (string) (config('services.linkpilot.analytics.hash_key_version') ?: 'local'),
                    'browser' => 'Chrome',
                    'platform' => 'macOS',
                    'country_code' => 'US',
                ]);
            }

            $this->command?->info('Seeded consented analytics events for the demo workspace.');
        }

        foreach (QrCode::query()->where('workspace_id', $workspace->id)->get() as $qrCode) {
            if ($qrCode->scans()->exists() || $qrCode->scan_count === 0) {
                continue;
            }

            foreach (range(1, min($qrCode->scan_count, 60)) as $index) {
                QrScan::query()->create([
                    'qr_code_id' => $qrCode->id,
                    'scanned_at' => now()->subDays($index % self::CLICK_WINDOW_DAYS)->setTime(10 + ($index % 9), ($index * 11) % 60),
                ]);
            }
        }

    }
}
