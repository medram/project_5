<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WORKSPACE_OWNED_TABLES = [
        'links',
        'bio_pages',
        'folders',
        'custom_domains',
        'tracking_pixels',
        'link_pages',
        'link_overlays',
        'webhooks',
        'analytics_events',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('user_subscriptions')) {
            throw new LogicException('Legacy user subscriptions must exist until workspace subscriptions are verified.');
        }

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            if (! Schema::hasColumn($tableName, 'workspace_id')) {
                throw new LogicException("Workspace ownership column is missing from [{$tableName}].");
            }

            $missing = DB::table($tableName)->whereNull('workspace_id')->count();
            if ($missing > 0) {
                throw new RuntimeException("Cannot contract workspace ownership: [{$tableName}] has {$missing} rows without a workspace.");
            }
        }

        $missingSubscriptions = DB::table('workspaces')
            ->leftJoin('workspace_subscriptions', 'workspace_subscriptions.workspace_id', '=', 'workspaces.id')
            ->whereNull('workspace_subscriptions.id')
            ->count();

        if ($missingSubscriptions > 0) {
            throw new RuntimeException("Cannot contract workspace billing: {$missingSubscriptions} workspaces have no subscription.");
        }

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable(false)->change();
            });
        }

        Schema::drop('user_subscriptions');
    }

    public function down(): void
    {
        $this->assertLegacyRollbackIsLossless();

        if (Schema::hasTable('user_subscriptions')) {
            throw new LogicException('Legacy user subscriptions already exist; refusing to overwrite rollback data.');
        }

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('source', 20);
            $table->timestamps();
        });

        DB::table('workspaces')
            ->join('workspace_subscriptions', 'workspace_subscriptions.workspace_id', '=', 'workspaces.id')
            ->whereNotNull('workspaces.personal_owner_user_id')
            ->select([
                'workspaces.personal_owner_user_id as user_id',
                'workspace_subscriptions.plan_id',
                'workspace_subscriptions.status',
                'workspace_subscriptions.source',
                'workspace_subscriptions.created_at',
                'workspace_subscriptions.updated_at',
            ])
            ->orderBy('workspaces.personal_owner_user_id')
            ->get()
            ->each(function (object $subscription): void {
                DB::table('user_subscriptions')->insert([
                    'user_id' => $subscription->user_id,
                    'plan_id' => $subscription->plan_id,
                    'status' => $subscription->status,
                    'source' => $subscription->source,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at,
                ]);
            });

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable()->change();
            });
        }
    }

    private function assertLegacyRollbackIsLossless(): void
    {
        if (! Schema::hasTable('workspaces')) {
            throw new LogicException('Workspace tables are required to restore legacy subscriptions.');
        }

        $hasTeamWorkspace = DB::table('workspaces')->whereNull('personal_owner_user_id')->exists();
        $hasMultipleMembers = DB::table('workspace_user')
            ->select('workspace_id')
            ->groupBy('workspace_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $hasNonOwnerMembership = DB::table('workspace_user')->where('role', '!=', 'owner')->exists();
        $hasMismatchedOwner = DB::table('workspace_user')
            ->join('workspaces', 'workspaces.id', '=', 'workspace_user.workspace_id')
            ->where('workspace_user.role', 'owner')
            ->whereColumn('workspace_user.user_id', '!=', 'workspaces.personal_owner_user_id')
            ->exists();
        $hasOwnerlessWorkspace = DB::table('workspaces')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('workspace_user')
                    ->whereColumn('workspace_user.workspace_id', 'workspaces.id')
                    ->where('workspace_user.role', 'owner');
            })
            ->exists();
        $hasInvitation = DB::table('workspace_invitations')->exists();
        $hasAudit = DB::table('workspace_audits')->exists();
        $hasMissingPersonalWorkspace = DB::table('users')
            ->leftJoin('workspaces', 'workspaces.personal_owner_user_id', '=', 'users.id')
            ->whereNull('workspaces.id')
            ->exists();
        $hasMissingSubscription = DB::table('workspaces')
            ->leftJoin('workspace_subscriptions', 'workspace_subscriptions.workspace_id', '=', 'workspaces.id')
            ->whereNull('workspace_subscriptions.id')
            ->exists();
        $hasNonOwnerData = false;

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            $hasUnassignedRows = DB::table($tableName)->whereNull('workspace_id')->exists();
            $hasForeignOwnerRows = DB::table($tableName)
                ->join('workspaces', 'workspaces.id', '=', "{$tableName}.workspace_id")
                ->where(function ($query) use ($tableName): void {
                    $query->whereNull("{$tableName}.user_id")
                        ->orWhereColumn("{$tableName}.user_id", '!=', 'workspaces.personal_owner_user_id');
                })
                ->exists();

            if ($hasUnassignedRows || $hasForeignOwnerRows) {
                $hasNonOwnerData = true;
                break;
            }
        }

        if (
            $hasTeamWorkspace
            || $hasMultipleMembers
            || $hasNonOwnerMembership
            || $hasMismatchedOwner
            || $hasOwnerlessWorkspace
            || $hasInvitation
            || $hasAudit
            || $hasMissingPersonalWorkspace
            || $hasMissingSubscription
            || $hasNonOwnerData
        ) {
            throw new LogicException('Workspace ownership or billing cannot be rolled back without losing collaboration or owner-scoped data.');
        }
    }
};
