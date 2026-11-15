<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspace_subscriptions')) {
            return;
        }

        if (! Schema::hasTable('workspace_plan_overrides')) {
            throw ValidationException::withMessages([
                'workspace_plan_overrides' => 'The plan-override table must exist before legacy subscriptions can be contracted.',
            ]);
        }

        $freePlanId = DB::table('plans')->where('code', 'free')->value('id');

        if ($freePlanId === null) {
            throw ValidationException::withMessages(['plans' => 'The free plan must exist before legacy subscriptions can be contracted.']);
        }

        DB::transaction(function () use ($freePlanId): void {
            $rows = DB::table('workspace_subscriptions')
                ->join('workspaces', 'workspaces.id', '=', 'workspace_subscriptions.workspace_id')
                ->join('plans', 'plans.id', '=', 'workspace_subscriptions.plan_id')
                ->select([
                    'workspace_subscriptions.workspace_id',
                    'workspace_subscriptions.plan_id',
                    'workspace_subscriptions.status',
                    'workspace_subscriptions.source',
                    'workspace_subscriptions.created_at',
                    'workspace_subscriptions.updated_at',
                    'plans.code as plan_code',
                ])
                ->orderBy('workspace_subscriptions.id')
                ->get();

            foreach ($rows as $row) {
                if ((int) $row->plan_id === (int) $freePlanId) {
                    continue;
                }

                $hasCurrentOverride = DB::table('workspace_plan_overrides')
                    ->where('workspace_id', $row->workspace_id)
                    ->where('plan_id', $row->plan_id)
                    ->whereNull('revoked_at')
                    ->where(fn ($query) => $query
                        ->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now()))
                    ->where(fn ($query) => $query
                        ->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now()))
                    ->exists();

                if ($hasCurrentOverride) {
                    continue;
                }

                DB::table('workspace_plan_overrides')->insert([
                    'workspace_id' => $row->workspace_id,
                    'plan_id' => $row->plan_id,
                    'actor_user_id' => null,
                    'source' => 'legacy_manual',
                    'reason' => 'Preserved from the contracted legacy workspace subscription.',
                    'starts_at' => $row->created_at ?? now(),
                    'expires_at' => null,
                    'revoked_at' => null,
                    'revoked_by_user_id' => null,
                    'revocation_reason' => null,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }
        });

        Schema::drop('workspace_subscriptions');
    }

    public function down(): void
    {
        Schema::create('workspace_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('source', 20);
            $table->timestamps();
        });

        $freePlanId = DB::table('plans')->where('code', 'free')->value('id');

        if ($freePlanId === null) {
            return;
        }

        $now = now();

        DB::table('workspaces')
            ->orderBy('id')
            ->chunk(200, function ($workspaces) use ($freePlanId, $now): void {
                foreach ($workspaces as $workspace) {
                    $override = DB::table('workspace_plan_overrides')
                        ->where('workspace_id', $workspace->id)
                        ->whereNull('revoked_at')
                        ->where(fn ($query) => $query
                            ->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', $now))
                        ->where(fn ($query) => $query
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $now))
                        ->orderByDesc('starts_at')
                        ->orderByDesc('id')
                        ->first();

                    DB::table('workspace_subscriptions')->insert([
                        'workspace_id' => $workspace->id,
                        'plan_id' => $override?->plan_id ?? $freePlanId,
                        'status' => 'active',
                        'source' => $override === null ? 'manual' : (string) $override->source,
                        'created_at' => $override?->created_at ?? $now,
                        'updated_at' => $override?->updated_at ?? $now,
                    ]);
                }
            });
    }
};
