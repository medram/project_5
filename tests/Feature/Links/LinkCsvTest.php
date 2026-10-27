<?php

namespace Tests\Feature\Links;

use App\Models\Folder;
use App\Models\LinkOverlay;
use App\Models\LinkPage;
use App\Models\User;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_applies_search_filter_and_workspace_scope_without_access_code_data(): void
    {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $hash = Hash::make('private-code');
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Campaign']);
        $tag = $workspace->linkTags()->create(['name' => 'Launch', 'slug' => 'launch']);
        $summer = $workspace->links()->create([
            'user_id' => $owner->id,
            'destination_url' => 'https://example.com/summer',
            'title' => 'Summer launch',
            'slug' => 'summer-link',
            'is_active' => true,
            'folder_id' => $folder->id,
            'access_code' => $hash,
        ]);
        $summer->tags()->attach($tag->id);

        $otherOwner = User::factory()->create();
        app(WorkspaceContext::class)->currentFor($otherOwner)->links()->create([
            'user_id' => $otherOwner->id,
            'destination_url' => 'https://example.com/private',
            'title' => 'Summer private route',
            'slug' => 'foreign-summer',
            'is_active' => true,
        ]);
        $workspace->links()->create([
            'user_id' => $owner->id,
            'destination_url' => 'https://example.com/winter',
            'title' => 'Winter launch',
            'slug' => 'winter-link',
            'is_active' => true,
        ]);

        $query = http_build_query([
            'search' => 'Summer',
            'folder' => $folder->id,
            'tag' => $tag->id,
            'status' => 'active',
        ]);
        $response = $this->actingAs($owner)->get('/dashboard/links/export?'.$query);
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $secondExport = $this->get('/dashboard/links/export?'.$query)->streamedContent();
        $this->assertSame($csv, $secondExport);

        $this->assertStringContainsString('summer-link', $csv);
        $this->assertStringNotContainsString('winter-link', $csv);
        $this->assertStringNotContainsString('foreign-summer', $csv);
        $this->assertStringNotContainsString('private-code', $csv);
        $this->assertStringNotContainsString($hash, $csv);
        $this->assertStringNotContainsString('access_code', strtok($csv, "\r\n"));
    }

    public function test_export_prefixes_formula_like_titles_with_a_text_marker(): void
    {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $titles = [
            '=1+1',
            '+1+1',
            '-1+1',
            '@SUM(A1:A2)',
            "\t-1+1",
            'Ordinary title',
        ];

        foreach ($titles as $index => $title) {
            $workspace->links()->create([
                'user_id' => $owner->id,
                'destination_url' => 'https://example.com/'.$index,
                'title' => $title,
                'slug' => 'formula-title-'.$index,
                'is_active' => true,
            ]);
        }

        $csv = $this->actingAs($owner)->get('/dashboard/links/export')->streamedContent();
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        fgetcsv($stream, null, ',', '"', '');

        $exportedTitles = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $exportedTitles[] = $row[1];
        }
        fclose($stream);

        $this->assertSame([
            "'=1+1",
            "'+1+1",
            "'-1+1",
            "'@SUM(A1:A2)",
            "'\t-1+1",
            'Ordinary title',
        ], $exportedTitles);
    }

    public function test_import_reports_each_row_and_creates_only_valid_workspace_links(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $csv = "slug,title,destination_url,is_active\n"
            .'valid-csv,"A valid, imported link",https://example.com/valid,1'."\n"
            .'valid-csv,Duplicate slug,https://example.com/duplicate,1'."\n"
            .'bad-url,Rejected URL,ftp://example.com/file,1'."\n";

        $this->actingAs($owner)
            ->post('/dashboard/links/import', [
                'file' => UploadedFile::fake()->createWithContent('links.csv', $csv),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/import-results')
                ->where('summary.created', 1)
                ->where('summary.failed', 2)
                ->where('results.0.status', 'created')
                ->where('results.0.slug', 'valid-csv')
                ->where('results.1.status', 'error')
                ->where('results.2.status', 'error')
                ->where('results.2.errors.0', 'Validation failed for: destination_url.'));

        $this->assertDatabaseHas('links', [
            'slug' => 'valid-csv',
            'title' => 'A valid, imported link',
            'workspace_id' => $owner->currentWorkspace->id,
        ]);
        $this->assertDatabaseMissing('links', ['slug' => 'bad-url']);
        $this->assertDatabaseCount('links', 1);
    }

    public function test_import_preserves_validated_link_list_state_and_discards_unknown_query_keys(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $folder = $workspace->folders()->create(['user_id' => $owner->id, 'name' => 'Campaign']);
        $tag = $workspace->linkTags()->create(['name' => 'Launch', 'slug' => 'launch']);

        $this->actingAs($owner)
            ->post('/dashboard/links/import?'.http_build_query([
                'search' => 'summer',
                'folder' => $folder->id,
                'tag' => $tag->id,
                'status' => 'pending',
                'sort' => 'title',
                'direction' => 'asc',
                'page' => 3,
                'view' => 'table',
                'admin' => 'true',
                'destination_url' => 'https://attacker.example',
            ]), [
                'file' => UploadedFile::fake()->createWithContent('links.csv', "invalid\n"),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('listState.search', 'summer')
                ->where('listState.folder', (string) $folder->id)
                ->where('listState.tag', (string) $tag->id)
                ->where('listState.status', 'pending')
                ->where('listState.sort', 'title')
                ->where('listState.direction', 'asc')
                ->where('listState.page', '3')
                ->where('listState.view', 'table')
                ->missing('listState.admin')
                ->missing('listState.destination_url'));
    }

    public function test_import_discards_all_list_state_when_an_allowlisted_value_is_invalid(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post('/dashboard/links/import?search=summer&view=wide', [
                'file' => UploadedFile::fake()->createWithContent('links.csv', "invalid\n"),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('listState', []));
    }

    public function test_pending_export_includes_live_approved_links_with_pending_revisions(): void
    {
        $owner = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $link = $workspace->links()->create([
            'user_id' => $owner->id,
            'destination_url' => 'https://example.com/current',
            'title' => 'Proposed update',
            'slug' => 'pending-proposal',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        $link->revisions()->create([
            'workspace_id' => $workspace->id,
            'actor_user_id' => $owner->id,
            'revision_number' => 2,
            'status' => 'pending',
            'snapshot' => ['title' => 'Proposed update'],
        ]);

        $csv = $this->actingAs($owner)->get('/dashboard/links/export?status=pending')->streamedContent();

        $this->assertStringContainsString('pending-proposal', $csv);
    }

    public function test_import_discards_list_state_with_foreign_workspace_folder_or_tag_ids(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $foreignWorkspace = app(WorkspaceContext::class)->currentFor(User::factory()->create());
        $folder = $foreignWorkspace->folders()->create(['user_id' => $foreignWorkspace->owner_id, 'name' => 'Foreign']);
        $tag = $foreignWorkspace->linkTags()->create(['name' => 'Foreign', 'slug' => 'foreign']);

        $this->actingAs($owner)
            ->post('/dashboard/links/import?'.http_build_query([
                'search' => 'summer',
                'folder' => $folder->id,
                'tag' => $tag->id,
            ]), [
                'file' => UploadedFile::fake()->createWithContent('links.csv', "invalid\n"),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('listState', []));
    }

    public function test_header_only_csv_returns_a_clear_row_result(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post('/dashboard/links/import', [
                'file' => UploadedFile::fake()->createWithContent(
                    'links.csv',
                    "slug,destination_url,is_active\n",
                ),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.created', 0)
                ->where('summary.failed', 1)
                ->where('results.0.row', 2)
                ->where('results.0.errors.0', 'The CSV contains no data rows.'));
    }

    public function test_import_preserves_tags_parameters_and_normalized_routing_rules(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $tag = $workspace->linkTags()->create(['name' => 'Seasonal', 'slug' => 'seasonal']);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'slug',
            'destination_url',
            'delivery_mode',
            'is_active',
            'utm_parameters',
            'custom_parameters',
            'routing_rules',
            'tag_ids',
        ], ',', '"', '', "\n");
        fputcsv($stream, [
            'routed-csv',
            'https://example.com/fallback',
            'direct',
            '1',
            json_encode(['utm_source' => 'newsletter'], JSON_THROW_ON_ERROR),
            json_encode(['campaign' => 'autumn'], JSON_THROW_ON_ERROR),
            json_encode([[
                'rule_type' => 'country',
                'match_value' => 'us',
                'target_url' => 'https://example.org/us',
            ]], JSON_THROW_ON_ERROR),
            json_encode([$tag->id], JSON_THROW_ON_ERROR),
        ], ',', '"', '', "\n");
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->actingAs($owner)
            ->post('/dashboard/links/import', [
                'file' => UploadedFile::fake()->createWithContent('links.csv', $csv),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.created', 1)
                ->where('summary.failed', 0));

        $link = $workspace->links()->where('slug', 'routed-csv')->sole();
        $this->assertSame(['utm_source' => 'newsletter'], $link->utm_parameters);
        $this->assertSame(['campaign' => 'autumn'], $link->custom_parameters);
        $this->assertTrue($link->tags()->whereKey($tag->id)->exists());
        $this->assertDatabaseHas('link_routing_rules', [
            'workspace_id' => $workspace->id,
            'link_id' => $link->id,
            'rule_type' => 'country',
            'match_value' => 'US',
            'target_url' => 'https://example.org/us',
        ]);
    }

    public function test_import_reports_quota_failure_per_row_without_creating_over_limit(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        for ($index = 0; $index < 25; $index++) {
            $workspace->links()->create([
                'user_id' => $owner->id,
                'destination_url' => "https://example.com/{$index}",
                'slug' => "quota-link-{$index}",
                'is_active' => true,
            ]);
        }

        $csv = "slug,destination_url,is_active\nquota-overflow,https://example.com/overflow,1\n";
        $this->actingAs($owner)
            ->post('/dashboard/links/import', [
                'file' => UploadedFile::fake()->createWithContent('links.csv', $csv),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.created', 0)
                ->where('summary.failed', 1)
                ->where('results.0.status', 'error')
                ->where('results.0.errors.0', 'The current workspace plan allows 25 Short links.'));

        $this->assertDatabaseCount('links', 25);
        $this->assertDatabaseMissing('links', ['slug' => 'quota-overflow']);
    }

    public function test_import_rejects_foreign_workspace_folder_tag_page_and_overlay_references(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->currentFor($owner);
        $foreignOwner = User::factory()->create();
        $foreignWorkspace = app(WorkspaceContext::class)->currentFor($foreignOwner);
        $folder = Folder::create([
            'user_id' => $foreignOwner->id,
            'workspace_id' => $foreignWorkspace->id,
            'name' => 'Foreign folder',
        ]);
        $tag = $foreignWorkspace->linkTags()->create(['name' => 'Foreign tag', 'slug' => 'foreign-tag']);
        $page = LinkPage::factory()->create([
            'user_id' => $foreignOwner->id,
            'workspace_id' => $foreignWorkspace->id,
            'is_published' => true,
        ]);
        $overlay = LinkOverlay::factory()->create([
            'user_id' => $foreignOwner->id,
            'workspace_id' => $foreignWorkspace->id,
            'is_active' => true,
        ]);

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'slug', 'destination_url', 'delivery_mode', 'is_active',
            'folder_id', 'tag_ids', 'link_page_id', 'link_overlay_id',
        ]);
        fputcsv($stream, ['foreign-folder', 'https://example.com/folder', 'direct', '1', $folder->id, '[]', '', '']);
        fputcsv($stream, ['foreign-tag', 'https://example.com/tag', 'direct', '1', '', json_encode([$tag->id]), '', '']);
        fputcsv($stream, ['foreign-page', '', 'link_page', '1', '', '[]', $page->id, '']);
        fputcsv($stream, ['foreign-overlay', 'https://example.com/overlay', 'overlay', '1', '', '[]', '', $overlay->id]);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        $this->actingAs($owner)
            ->post('/dashboard/links/import', [
                'file' => UploadedFile::fake()->createWithContent('links.csv', $csv),
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('links/import-results')
                ->has('results', 4)
                ->where('results.0.status', 'error')
                ->where('results.1.status', 'error')
                ->where('results.2.status', 'error')
                ->where('results.3.status', 'error')
                ->where('results.0.errors.0', 'Validation failed for: folder_id.')
                ->where('results.1.errors.0', 'Validation failed for: tag_ids.0.')
                ->where('results.2.errors.0', 'Validation failed for: link_page_id.')
                ->where('results.3.errors.0', 'Validation failed for: link_overlay_id.'));

        $this->assertSame(0, $workspace->links()->count());
    }
}
