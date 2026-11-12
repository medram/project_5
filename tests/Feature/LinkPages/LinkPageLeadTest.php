<?php

namespace Tests\Feature\LinkPages;

use App\Models\LinkPage;
use App\Models\LinkPageBlock;
use App\Models\LinkPageLead;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkPageLeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.linkpilot.analytics.privacy_url', 'https://linkpilot.test/privacy');
    }

    public function test_valid_lead_submission_is_stored_encrypted_with_separate_consent_and_no_analytics_event(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $block = $this->persistFormBlock($page);
        $values = [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'interest' => 'Launch',
        ];

        $this->submit($page, $block, $values)
            ->assertRedirect("/pages/{$page->slug}");

        $lead = LinkPageLead::query()->sole();
        $stored = DB::table('link_page_leads')->where('id', $lead->id)->value('responses');
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('Ada Lovelace', $stored);
        $this->assertStringNotContainsString('ada@example.test', $stored);
        $this->assertSame($values, $lead->responses);
        $this->assertSame($page->workspace_id, $lead->workspace_id);
        $this->assertSame($page->id, $lead->link_page_id);
        $this->assertSame($block->id, $lead->link_page_block_id);
        $this->assertTrue($lead->consented_at->equalTo(now('UTC')));
        $this->assertTrue($lead->retention_expires_at->equalTo(now('UTC')->addDays(30)));
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_public_submission_requires_lead_consent_and_rejects_forged_or_unlisted_values(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $block = $this->persistFormBlock($page);
        $values = ['name' => 'Ada', 'email' => 'ada@example.test', 'interest' => 'Launch'];

        $this->post("/pages/{$page->slug}/blocks/{$block->id}/leads", ['values' => $values])
            ->assertSessionHasErrors('lead_consent');
        $this->assertNull(session()->getOldInput('values.name'));

        $this->post("/pages/{$page->slug}/blocks/{$block->id}/leads", [
            'lead_consent' => true,
            'values' => [...$values, 'forged' => 'must not persist'],
        ])->assertSessionHasErrors('values');
        $this->assertNull(session()->getOldInput('values.name'));

        $this->post("/pages/{$page->slug}/blocks/{$block->id}/leads", [
            'lead_consent' => true,
            'values' => [...$values, 'interest' => 'Injected option'],
        ])->assertSessionHasErrors('values.interest');
        $this->assertNull(session()->getOldInput('values.name'));

        $this->assertDatabaseCount('link_page_leads', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    public function test_owner_can_create_a_fixed_schema_form_and_public_page_exposes_only_that_schema(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $fields = $this->fields();

        $this->actingAs($owner)
            ->post("/dashboard/link-pages/{$page->id}/blocks", [
                'type' => 'form',
                'fields' => $fields,
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/link-pages/{$page->id}")
            ->assertSessionHasNoErrors();

        $block = LinkPageBlock::query()->where('link_page_id', $page->id)->where('type', 'form')->sole();
        $this->withoutVite();
        $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response
                ->where('items.0.type', 'form')
                ->where('items.0.fields', $fields)
                ->where('items.0.submitUrl', "/pages/{$page->slug}/blocks/{$block->id}/leads")
                ->missing('items.0.responses')
            );
    }

    public function test_unexpired_leads_prevent_form_schema_and_type_changes(): void
    {
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $block = $this->persistFormBlock($page);
        $this->submit($page, $block, ['name' => 'Ada', 'email' => 'ada@example.test', 'interest' => 'Launch']);

        $changedFields = $this->fields();
        $changedFields[0]['label'] = 'Full name';
        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/blocks/{$block->id}", [
                'type' => 'form',
                'fields' => $changedFields,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('fields');
        $this->assertSame($this->fields(), $block->fresh()->content['fields']);

        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/blocks/{$block->id}", [
                'type' => 'text',
                'text' => 'Changed block type',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('type');
        $this->assertSame('form', $block->fresh()->type);

        $this->travel(30)->days();
        $this->actingAs($owner)
            ->patch("/dashboard/link-pages/{$page->id}/blocks/{$block->id}", [
                'type' => 'form',
                'fields' => $changedFields,
                'is_active' => true,
            ])
            ->assertRedirect("/dashboard/link-pages/{$page->id}");
        $this->assertSame('Full name', $block->fresh()->content['fields'][0]['label']);
    }

    public function test_workspace_owner_and_admin_can_view_and_export_only_their_page_leads(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $platformAdmin = User::factory()->admin()->create();
        $workspace = $this->teamWorkspace($owner);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);
        $workspace->members()->attach($member->id, ['role' => 'member']);
        $workspace->members()->attach($platformAdmin->id, ['role' => 'member']);
        foreach ([$owner, $admin, $member, $platformAdmin] as $user) {
            $this->setCurrentWorkspace($user, $workspace);
        }

        $page = $this->createPage($owner);
        $block = $this->persistFormBlock($page);
        $this->submit($page, $block, ['name' => 'Workspace lead', 'email' => 'lead@example.test', 'interest' => 'Launch']);

        foreach ([$owner, $admin] as $authorizedUser) {
            $this->withoutVite();
            $this->actingAs($authorizedUser)
                ->get("/dashboard/link-pages/{$page->id}/leads")
                ->assertOk()
                ->assertInertia(fn (Assert $response) => $response
                    ->component('link-pages/leads', false)
                    ->has('leads.data', 1)
                    ->where('leads.data.0.values.name', 'Workspace lead')
                );
            $this->actingAs($authorizedUser)
                ->get("/dashboard/link-pages/{$page->id}/leads/export")
                ->assertOk()
                ->assertDownload("link-page-leads-{$page->slug}.csv");
        }

        foreach ([$member, $platformAdmin] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get("/dashboard/link-pages/{$page->id}/leads")
                ->assertForbidden();
            $this->actingAs($unauthorizedUser)
                ->get("/dashboard/link-pages/{$page->id}/leads/export")
                ->assertForbidden();
        }

        $otherOwner = User::factory()->create();
        $otherPage = $this->createPage($otherOwner);
        $this->actingAs($owner)
            ->get("/dashboard/link-pages/{$otherPage->id}/leads")
            ->assertNotFound();
    }

    public function test_csv_export_orders_submissions_and_neutralizes_spreadsheet_formulas(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $fields = [['id' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true]];
        $block = $this->persistFormBlock($page, $fields);
        $this->submit($page, $block, ['name' => 'First submission']);
        $this->submit($page, $block, ['name' => '=1+1']);

        $response = $this->actingAs($owner)
            ->get("/dashboard/link-pages/{$page->id}/leads/export")
            ->assertOk()
            ->assertDownload("link-page-leads-{$page->slug}.csv");
        $csv = $response->streamedContent();

        $this->assertStringContainsString("form_{$block->id}_name", $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertLessThan(strpos($csv, "'=1+1"), strpos($csv, 'First submission'));
    }

    public function test_pruner_removes_a_lead_at_the_exact_thirty_day_boundary_and_keeps_newer_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $block = $this->persistFormBlock($page);
        $this->submit($page, $block, ['name' => 'Expired', 'email' => 'old@example.test', 'interest' => 'Launch']);
        $expired = LinkPageLead::query()->sole();
        $this->assertTrue($expired->retention_expires_at->equalTo(now('UTC')->addDays(30)));

        $this->travel(30)->days();
        $this->submit($page, $block, ['name' => 'Retained', 'email' => 'new@example.test', 'interest' => 'Launch']);
        $retained = LinkPageLead::query()->latest('id')->firstOrFail();

        $this->artisan('linkpilot:prune-link-page-leads');

        $this->assertDatabaseMissing('link_page_leads', ['id' => $expired->id]);
        $this->assertDatabaseHas('link_page_leads', ['id' => $retained->id]);
        $this->assertSame(1, LinkPageLead::query()->count());
    }

    public function test_production_without_privacy_url_hides_the_form_and_rejects_direct_submission(): void
    {
        config()->set('app.env', 'production');
        config()->set('services.linkpilot.analytics.privacy_url', null);
        $owner = User::factory()->create();
        $page = $this->createPage($owner);
        $block = $this->persistFormBlock($page);

        $this->withoutVite();
        $this->get("/pages/{$page->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $response) => $response->where('items', []));
        $this->post("/pages/{$page->slug}/blocks/{$block->id}/leads", [
            'lead_consent' => true,
            'values' => ['name' => 'Ada', 'email' => 'ada@example.test', 'interest' => 'Launch'],
        ])->assertNotFound();

        $this->assertDatabaseCount('link_page_leads', 0);
    }

    /** @return list<array{id: string, label: string, type: string, required: bool, options?: list<string>}> */
    private function fields(): array
    {
        return [
            ['id' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['id' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => false],
            ['id' => 'interest', 'label' => 'Interest', 'type' => 'select', 'required' => true, 'options' => ['Launch', 'Partnership']],
        ];
    }

    private function createPage(User $owner): LinkPage
    {
        $slug = 'lead-'.Str::lower(Str::random(8));
        $this->actingAs($owner)
            ->post('/dashboard/link-pages', [
                'name' => 'Lead collection',
                'slug' => $slug,
                'title' => 'Request more information',
                'description' => 'Contact the team.',
                'template' => 'standard',
                'is_published' => true,
            ])
            ->assertRedirect();

        return LinkPage::query()->where('slug', $slug)->sole();
    }

    /** @param list<array{id: string, label: string, type: string, required: bool, options?: list<string>}>|null $fields */
    private function persistFormBlock(LinkPage $page, ?array $fields = null): LinkPageBlock
    {
        return LinkPageBlock::query()->create([
            'link_page_id' => $page->id,
            'type' => 'form',
            'content' => ['fields' => $fields ?? $this->fields()],
            'position' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, string>  $values
     * @return TestResponse<RedirectResponse>
     */
    private function submit(LinkPage $page, LinkPageBlock $block, array $values): TestResponse
    {
        return $this->post("/pages/{$page->slug}/blocks/{$block->id}/leads", [
            'lead_consent' => true,
            'values' => $values,
        ]);
    }

    private function teamWorkspace(User $owner): Workspace
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Lead access team',
            'slug' => 'lead-access-'.$owner->id,
            'personal_owner_user_id' => null,
            'owner_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Workspace::query()->findOrFail($workspaceId);
    }

    private function setCurrentWorkspace(User $user, Workspace $workspace): void
    {
        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    }
}
