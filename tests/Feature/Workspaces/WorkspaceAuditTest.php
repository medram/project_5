<?php

namespace Tests\Feature\Workspaces;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class WorkspaceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_audit_records_cannot_be_updated(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->firstOrFail();
        $audit = $workspace->audits()->create([
            'actor_user_id' => $user->id,
            'action' => 'member.role_changed',
            'metadata' => ['from' => 'member', 'to' => 'admin'],
        ]);

        $this->expectException(LogicException::class);
        $audit->update(['action' => 'ownership.transferred']);
    }

    public function test_workspace_audit_records_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $workspace = $user->personalWorkspace()->firstOrFail();
        $audit = $workspace->audits()->create([
            'actor_user_id' => $user->id,
            'action' => 'member.removed',
            'metadata' => ['role' => 'member'],
        ]);

        $this->expectException(LogicException::class);
        $audit->delete();
    }
}
