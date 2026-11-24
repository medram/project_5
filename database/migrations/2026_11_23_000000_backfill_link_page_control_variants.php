<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now('UTC');

        DB::table('link_pages')->orderBy('id')->chunkById(200, function ($pages) use ($now): void {
            foreach ($pages as $page) {
                $controlId = DB::table('link_page_variants')
                    ->where('link_page_id', $page->id)
                    ->where('is_control', true)
                    ->whereNull('deleted_at')
                    ->value('id');

                if ($controlId === null) {
                    $controlId = DB::table('link_page_variants')->insertGetId([
                        'link_page_id' => $page->id,
                        'name' => 'Control',
                        'weight' => 100,
                        'is_control' => true,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    DB::table('link_page_variants')
                        ->where('id', $controlId)
                        ->where('is_active', false)
                        ->update(['is_active' => true, 'updated_at' => $now]);
                }

                DB::table('link_page_items')
                    ->where('link_page_id', $page->id)
                    ->whereNull('link_page_variant_id')
                    ->update(['link_page_variant_id' => $controlId]);

                DB::table('link_page_blocks')
                    ->where('link_page_id', $page->id)
                    ->whereNull('link_page_variant_id')
                    ->update(['link_page_variant_id' => $controlId]);
            }
        });
    }

    public function down(): void
    {
        // Existing pages keep their control variants so they remain editable.
    }
};
