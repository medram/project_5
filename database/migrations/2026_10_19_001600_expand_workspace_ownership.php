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
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->foreignId('personal_owner_user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255);
            $table->string('role', 20);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email', 'expires_at']);
        });

        Schema::create('workspace_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('source', 20);
            $table->timestamps();
        });

        Schema::create('workspace_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('current_workspace_id')
                ->nullable()
                ->constrained('workspaces')
                ->nullOnDelete();
        });

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')
                    ->nullable()
                    ->constrained('workspaces')
                    ->restrictOnDelete();
            });
        }

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('member_limit')->nullable();
        });

        DB::table('plans')->where('code', 'free')->update(['member_limit' => 1]);
        DB::table('plans')->where('code', 'pro')->update(['member_limit' => 5]);
        DB::table('plans')->where('code', 'business')->update(['member_limit' => null]);
    }

    public function down(): void
    {
        $this->assertLegacyRollbackIsLossless();
        if (Schema::hasTable('workspace_subscriptions') && ! Schema::hasTable('user_subscriptions')) {
            throw new LogicException('The workspace contract migration must be rolled back before ownership expansion.');
        }

        $legacySubscriptions = collect();

        if (Schema::hasTable('workspace_subscriptions')) {
            $legacySubscriptions = DB::table('workspace_subscriptions')
                ->join('workspaces', 'workspaces.id', '=', 'workspace_subscriptions.workspace_id')
                ->whereNotNull('workspaces.personal_owner_user_id')
                ->select([
                    'workspaces.personal_owner_user_id as user_id',
                    'workspace_subscriptions.plan_id',
                    'workspace_subscriptions.status',
                    'workspace_subscriptions.source',
                    'workspace_subscriptions.created_at',
                    'workspace_subscriptions.updated_at',
                ])
                ->orderBy('workspace_subscriptions.id')
                ->get();
        }

        if (Schema::hasColumn('plans', 'member_limit')) {
            Schema::table('plans', function (Blueprint $table): void {
                $table->dropColumn('member_limit');
            });
        }

        foreach (self::WORKSPACE_OWNED_TABLES as $tableName) {
            if (Schema::hasColumn($tableName, 'workspace_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('workspace_id');
                });
            }
        }

        if (Schema::hasColumn('users', 'current_workspace_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('current_workspace_id');
            });
        }

        Schema::dropIfExists('workspace_audits');
        Schema::dropIfExists('workspace_subscriptions');
        Schema::dropIfExists('workspace_invitations');
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
        $legacySubscriptions->each(function (object $subscription): void {
            DB::table('user_subscriptions')->updateOrInsert(
                ['user_id' => $subscription->user_id],
                [
                    'plan_id' => $subscription->plan_id,
                    'status' => $subscription->status,
                    'source' => $subscription->source,
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at,
                ],
            );
        });
    }

    private function assertLegacyRollbackIsLossless(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
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
            ->whereNotNull('workspaces.personal_owner_user_id')
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
        if ($hasTeamWorkspace || $hasMultipleMembers || $hasNonOwnerMembership || $hasMismatchedOwner || $hasOwnerlessWorkspace || $hasInvitation || $hasAudit || $hasNonOwnerData) {
            throw new LogicException('Workspace ownership cannot be rolled back after collaboration or non-owner data has been created.');
        }
    }
};
