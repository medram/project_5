<?php

namespace Tests\Feature\Admin;

use App\Models\OperationLog;
use App\Models\User;
use App\Services\Logging\OperationLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authorized_administrators_can_read_and_export_operational_logs(): void
    {
        $support = User::factory()->support()->create();
        $administrator = User::factory()->admin()->create();
        app(OperationLogger::class)->record('error', 'queue', 'job.failed', 'A queued job failed.', ['token' => 'secret', 'job' => 'SendThing']);

        $this->actingAs($support)->get('/admin/logs')->assertOk();
        $this->actingAs($support)->get('/admin/logs/export')->assertForbidden();

        $this->actingAs($administrator)->get('/admin/logs?level=error&channel=queue')
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/logs/index')
                ->has('rows.data', 1)
                ->where('rows.data.0.level', 'error')
                ->where('rows.data.0.context.token', '[redacted]'));

        $export = $this->actingAs($administrator)->get('/admin/logs/export?level=error');
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $export->streamedContent();
        $this->assertStringContainsString('job.failed', $csv);
        $this->assertStringNotContainsString('secret', $csv);
    }

    public function test_csv_export_neutralizes_formula_cells(): void
    {
        $administrator = User::factory()->admin()->create();
        app(OperationLogger::class)->record('info', 'mail', 'mail.formula', '=HYPERLINK("https://example.test")');

        $export = $this->actingAs($administrator)->get('/admin/logs/export?q=formula');
        $csv = $export->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_logs_are_append_only_and_redact_sensitive_context(): void
    {
        $log = app(OperationLogger::class)->record('warning', 'mail', 'mail.deferred', 'Mail deferred.', [
            'email' => 'person@example.test',
            'password' => 'hunter2',
            'headers' => ['Authorization' => 'Bearer secret'],
            'mailer' => 'smtp',
        ]);

        $this->assertSame('[redacted]', $log->context['email']);
        $this->assertSame('[redacted]', $log->context['password']);
        $this->assertSame('[redacted]', $log->context['headers']['Authorization']);
        $this->assertSame('smtp', $log->context['mailer']);

        $this->expectException(\LogicException::class);
        $log->update(['message' => 'changed']);
    }

    public function test_retention_command_prunes_only_old_logs(): void
    {
        OperationLog::query()->create(['level' => 'info', 'channel' => 'app', 'event' => 'old', 'message' => 'old']);
        OperationLog::query()->whereKey(1)->update(['created_at' => now()->subDays(400)]);
        OperationLog::query()->create(['level' => 'info', 'channel' => 'app', 'event' => 'new', 'message' => 'new']);

        $this->artisan('linkpilot:prune-operation-logs')->assertSuccessful();

        $this->assertDatabaseMissing('operation_logs', ['event' => 'old']);
        $this->assertDatabaseHas('operation_logs', ['event' => 'new']);
    }
}
