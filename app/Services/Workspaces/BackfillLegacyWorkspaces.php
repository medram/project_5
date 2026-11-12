<?php

namespace App\Services\Workspaces;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class BackfillLegacyWorkspaces
{
    private const OWNED_TABLES = [
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

    public function run(): void
    {
        $hasLegacySubscriptions = Schema::hasTable('user_subscriptions');

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use ($hasLegacySubscriptions): void {
                foreach ($users as $user) {
                    $this->backfillUser($user, $hasLegacySubscriptions);
                }
            });

        foreach (self::OWNED_TABLES as $table) {
            DB::table($table)
                ->select(['id', 'user_id'])
                ->whereNull('workspace_id')
                ->whereNotNull('user_id')
                ->orderBy('id')
                ->chunkById(500, function (Collection $rows) use ($table): void {
                    $workspaceIds = DB::table('workspaces')
                        ->whereIn('personal_owner_user_id', $rows->pluck('user_id')->unique()->all())
                        ->pluck('id', 'personal_owner_user_id');

                    foreach ($rows->groupBy('user_id') as $userId => $ownedRows) {
                        $workspaceId = $workspaceIds->get($userId);

                        if ($workspaceId === null) {
                            throw new LogicException("No personal workspace exists for legacy owner [{$userId}].");
                        }

                        DB::table($table)
                            ->whereIn('id', $ownedRows->pluck('id')->all())
                            ->whereNull('workspace_id')
                            ->update(['workspace_id' => $workspaceId]);
                    }
                });
        }
        $this->createMissingWorkspaceSubscriptions();
    }

    private function backfillUser(object $user, bool $hasLegacySubscriptions): void
    {
        DB::transaction(function () use ($user, $hasLegacySubscriptions): void {
            $workspaceId = DB::table('workspaces')
                ->where('personal_owner_user_id', $user->id)
                ->value('id');

            if ($workspaceId === null) {
                // owner_id does not exist at this migration's chronology point;
                // 2026_10_19_001900 derives the canonical owner from the owner
                // membership created below.
                $workspaceId = DB::table('workspaces')->insertGetId([
                    'name' => mb_substr("{$user->name}'s workspace", 0, 120),
                    'slug' => "personal-{$user->id}",
                    'personal_owner_user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $membership = DB::table('workspace_user')
                ->where('workspace_id', $workspaceId)
                ->where('user_id', $user->id)
                ->first();

            if ($membership === null) {
                DB::table('workspace_user')->insert([
                    'workspace_id' => $workspaceId,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } elseif ($membership->role !== 'owner') {
                DB::table('workspace_user')
                    ->where('workspace_id', $workspaceId)
                    ->where('user_id', $user->id)
                    ->update(['role' => 'owner', 'updated_at' => now()]);
            }

            $currentWorkspaceId = DB::table('users')->where('id', $user->id)->value('current_workspace_id');
            $currentWorkspaceIsMember = $currentWorkspaceId !== null
                && DB::table('workspace_user')
                    ->where('workspace_id', $currentWorkspaceId)
                    ->where('user_id', $user->id)
                    ->exists();

            if (! $currentWorkspaceIsMember) {
                DB::table('users')->where('id', $user->id)->update([
                    'current_workspace_id' => $workspaceId,
                    'updated_at' => now(),
                ]);
            }

            $legacySubscription = $hasLegacySubscriptions
                ? DB::table('user_subscriptions')
                    ->where('user_id', $user->id)
                    ->select(['plan_id', 'status', 'source', 'created_at', 'updated_at'])
                    ->first()
                : null;

            if ($legacySubscription !== null) {
                DB::table('workspace_subscriptions')->insertOrIgnore([
                    'workspace_id' => $workspaceId,
                    'plan_id' => $legacySubscription->plan_id,
                    'status' => $legacySubscription->status,
                    'source' => $legacySubscription->source,
                    'created_at' => $legacySubscription->created_at ?? now(),
                    'updated_at' => $legacySubscription->updated_at ?? now(),
                ]);
            }
        });
    }

    private function createMissingWorkspaceSubscriptions(): void
    {
        $freePlanId = DB::table('plans')->where('code', 'free')->value('id');

        if ($freePlanId === null) {
            throw new LogicException('The free plan must exist before workspace subscriptions are backfilled.');
        }

        DB::table('workspaces')
            ->leftJoin('workspace_subscriptions', 'workspace_subscriptions.workspace_id', '=', 'workspaces.id')
            ->whereNull('workspace_subscriptions.id')
            ->orderBy('workspaces.id')
            ->select('workspaces.id')
            ->chunkById(100, function (Collection $workspaces) use ($freePlanId): void {
                foreach ($workspaces as $workspace) {
                    DB::table('workspace_subscriptions')->insertOrIgnore([
                        'workspace_id' => $workspace->id,
                        'plan_id' => $freePlanId,
                        'status' => 'active',
                        'source' => 'manual',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'workspaces.id', 'id');

    }
}
