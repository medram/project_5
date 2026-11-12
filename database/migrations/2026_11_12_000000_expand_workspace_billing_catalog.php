<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The approved BIL-9 catalog. Prices are stored in minor currency units and
     * null limits remain unlimited.
     *
     * @var array<string, array{
     *     monthly_price: int,
     *     annual_price: int,
     *     link_limit: int|null,
     *     folder_limit: int|null,
     *     qr_campaign_limit: int|null,
     *     bio_page_limit: int|null,
     *     domain_limit: int|null,
     *     pixel_limit: int|null,
     *     member_limit: int|null,
     *     webhook_limit: int|null,
     *     link_page_limit: int|null,
     *     api_key_limit: int|null,
     *     storage_byte_limit: int|null
     * }>
     */
    private const CATALOG = [
        'free' => [
            'monthly_price' => 0,
            'annual_price' => 0,
            'link_limit' => 25,
            'folder_limit' => 5,
            'qr_campaign_limit' => 5,
            'bio_page_limit' => 1,
            'domain_limit' => 0,
            'pixel_limit' => 0,
            'member_limit' => 1,
            'webhook_limit' => 0,
            'link_page_limit' => 1,
            'api_key_limit' => 0,
            'storage_byte_limit' => 100 * 1024 * 1024,
        ],
        'pro' => [
            'monthly_price' => 2900,
            'annual_price' => 29000,
            'link_limit' => 500,
            'folder_limit' => 100,
            'qr_campaign_limit' => 250,
            'bio_page_limit' => 10,
            'domain_limit' => 3,
            'pixel_limit' => 10,
            'member_limit' => 5,
            'webhook_limit' => 5,
            'link_page_limit' => 25,
            'api_key_limit' => 5,
            'storage_byte_limit' => 10 * 1024 * 1024 * 1024,
        ],
        'business' => [
            'monthly_price' => 9900,
            'annual_price' => 99000,
            'link_limit' => null,
            'folder_limit' => null,
            'qr_campaign_limit' => null,
            'bio_page_limit' => null,
            'domain_limit' => null,
            'pixel_limit' => null,
            'member_limit' => null,
            'webhook_limit' => null,
            'link_page_limit' => null,
            'api_key_limit' => null,
            'storage_byte_limit' => null,
        ],
    ];

    public function up(): void
    {
        $caseSensitiveStripeIds = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $stripeId = fn (Blueprint $table, string $column): ColumnDefinition => $caseSensitiveStripeIds
            ? $table->string($column, 191)->nullable()->collation('utf8mb4_bin')
            : $table->string($column, 191)->nullable();

        Schema::table('plans', function (Blueprint $table) use ($stripeId): void {
            $table->unsignedBigInteger('annual_price')->default(0)->after('monthly_price');
            $table->unsignedInteger('folder_limit')->nullable();
            $table->unsignedInteger('bio_page_limit')->nullable();
            $table->unsignedInteger('api_key_limit')->nullable();
            $table->unsignedBigInteger('storage_byte_limit')->nullable();
            $table->json('display_metadata')->nullable();
            $stripeId($table, 'stripe_monthly_price_id');
            $stripeId($table, 'stripe_yearly_price_id');
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->string('stripe_id', 191)->nullable();
            $table->string('pm_type', 40)->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->unique('stripe_id');
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'stripe_status']);
        });

        Schema::create('subscription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->string('meter_id')->nullable();
            $table->string('meter_event_name')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'stripe_price']);
        });

        Schema::create('workspace_plan_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 40);
            $table->string('reason', 500);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'revoked_at', 'expires_at']);
        });

        Schema::create('billing_health_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);
            $table->string('stripe_price', 191)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'resolved_at']);
            $table->index(['workspace_id', 'type']);
        });

        Schema::table('content_media', function (Blueprint $table): void {
            $table->string('purpose', 30)->default('image');
        });

        $this->backfillPlans();
        $this->backfillOverrides();
        $this->backfillMediaPurposes();
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && DB::table('subscriptions')->exists()) {
            throw new LogicException('Refusing to drop provider subscription records; keep the Phase 8 billing schema until Stripe state is reconciled.');
        }

        $this->reconstructLegacySubscriptions();

        if (Schema::hasColumn('content_media', 'expires_at')) {
            DB::table('content_media')
                ->whereNull('expires_at')
                ->update(['expires_at' => now('UTC')->addDays(365)]);

            Schema::table('content_media', function (Blueprint $table): void {
                $table->timestamp('expires_at')->nullable(false)->change();
            });
        }

        Schema::table('content_media', function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });

        Schema::dropIfExists('billing_health_records');
        Schema::dropIfExists('workspace_plan_overrides');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropUnique(['stripe_id']);
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn([
                'annual_price',
                'folder_limit',
                'bio_page_limit',
                'api_key_limit',
                'storage_byte_limit',
                'display_metadata',
                'stripe_monthly_price_id',
                'stripe_yearly_price_id',
            ]);
        });
    }

    private function backfillPlans(): void
    {
        $metadata = [
            'free' => [
                'description' => 'Start publishing links with core controls.',
                'sort_order' => 0,
                'featured' => false,
            ],
            'pro' => [
                'description' => 'For creators and small teams running active campaigns.',
                'sort_order' => 1,
                'featured' => true,
            ],
            'business' => [
                'description' => 'Unlimited resources for high-volume publishing.',
                'sort_order' => 2,
                'featured' => false,
            ],
        ];

        foreach (self::CATALOG as $code => $limits) {
            $plan = DB::table('plans')->where('code', $code)->first();

            if ($plan === null) {
                continue;
            }

            DB::table('plans')->where('id', $plan->id)->update([
                ...$limits,
                'display_metadata' => json_encode($metadata[$code], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        }
    }

    private function backfillOverrides(): void
    {
        if (! Schema::hasTable('workspace_subscriptions')) {
            return;
        }

        $freePlanId = DB::table('plans')->where('code', 'free')->value('id');

        DB::table('workspace_subscriptions')
            ->join('plans', 'plans.id', '=', 'workspace_subscriptions.plan_id')
            ->where('plans.code', '!=', 'free')
            ->select([
                'workspace_subscriptions.workspace_id',
                'workspace_subscriptions.plan_id',
                'workspace_subscriptions.created_at',
            ])
            ->orderBy('workspace_subscriptions.id')
            ->chunk(200, function ($subscriptions): void {
                foreach ($subscriptions as $subscription) {
                    $exists = DB::table('workspace_plan_overrides')
                        ->where('workspace_id', $subscription->workspace_id)
                        ->where('plan_id', $subscription->plan_id)
                        ->where('source', 'legacy_manual')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('workspace_plan_overrides')->insert([
                        'workspace_id' => $subscription->workspace_id,
                        'plan_id' => $subscription->plan_id,
                        'actor_user_id' => null,
                        'source' => 'legacy_manual',
                        'reason' => 'Imported from the legacy workspace subscription.',
                        'starts_at' => $subscription->created_at,
                        'expires_at' => null,
                        'revoked_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        DB::table('workspace_plan_overrides')
            ->where('plan_id', $freePlanId)
            ->delete();
    }

    private function backfillMediaPurposes(): void
    {
        DB::table('content_media')
            ->where('mime_type', 'application/pdf')
            ->update(['purpose' => 'file']);

        DB::table('content_media')
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->update(['purpose' => 'image']);

        if (Schema::hasTable('link_page_blocks')) {
            DB::table('content_media')
                ->whereIn('id', DB::table('link_page_blocks')
                    ->where('type', 'file')
                    ->whereNotNull('content_media_id')
                    ->select('content_media_id'))
                ->update(['purpose' => 'file']);
        }

        if (Schema::hasTable('bio_content_blocks')) {
            DB::table('content_media')
                ->whereIn('id', DB::table('bio_content_blocks')
                    ->whereNotNull('content_media_id')
                    ->select('content_media_id'))
                ->update(['purpose' => 'image']);
        }

        DB::table('content_media')->whereNull('purpose')->update(['purpose' => 'file']);
    }

    /**
     * Restore an equivalent legacy entitlement row for every workspace that
     * currently resolves through a non-Free override.
     */
    private function reconstructLegacySubscriptions(): void
    {
        if (! Schema::hasTable('workspace_plan_overrides') || ! Schema::hasTable('workspace_subscriptions')) {
            return;
        }

        $now = now();

        $activeOverrides = DB::table('workspace_plan_overrides')
            ->join('plans', 'plans.id', '=', 'workspace_plan_overrides.plan_id')
            ->where('plans.code', '!=', 'free')
            ->whereNull('workspace_plan_overrides.revoked_at')
            ->where(function ($query) use ($now): void {
                $query->whereNull('workspace_plan_overrides.starts_at')
                    ->orWhere('workspace_plan_overrides.starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('workspace_plan_overrides.expires_at')
                    ->orWhere('workspace_plan_overrides.expires_at', '>', $now);
            })
            ->orderBy('workspace_plan_overrides.starts_at')
            ->orderBy('workspace_plan_overrides.id')
            ->select([
                'workspace_plan_overrides.workspace_id',
                'workspace_plan_overrides.plan_id',
            ])
            ->get()
            ->keyBy('workspace_id');

        foreach ($activeOverrides as $workspaceId => $override) {
            DB::table('workspace_subscriptions')->updateOrInsert(
                ['workspace_id' => $workspaceId],
                [
                    'plan_id' => $override->plan_id,
                    'status' => 'active',
                    'source' => 'manual',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
};
