<?php

namespace App\Services\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceUsage;
use Illuminate\Support\Facades\DB;

class CreatesPersonalWorkspace
{
    public function __construct(private readonly WorkspaceUsage $usage) {}

    public function for(User $user): Workspace
    {
        return DB::transaction(function () use ($user): Workspace {
            $workspace = Workspace::query()->firstOrCreate(
                ['personal_owner_user_id' => $user->id],
                [
                    'owner_id' => $user->id,
                    'name' => mb_substr("{$user->name}'s workspace", 0, 120),
                    'slug' => "personal-{$user->id}",
                ],
            );

            $workspace->members()->syncWithoutDetaching([
                $user->id => ['role' => 'owner'],
            ]);
            $this->usage->subscriptionFor($workspace);
            $currentWorkspaceId = $user->current_workspace_id;

            if ($currentWorkspaceId === null || ! $user->workspaces()->whereKey($currentWorkspaceId)->exists()) {
                $user->forceFill(['current_workspace_id' => $workspace->getKey()])->save();
            }

            return $workspace;
        });
    }
}
