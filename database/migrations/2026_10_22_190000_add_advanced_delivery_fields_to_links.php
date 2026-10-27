<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table): void {
            $table->string('destination_url', 2048)->nullable()->change();
            $table->string('delivery_mode', 24)->default('direct');
            $table->foreignId('link_page_id')->nullable()->constrained('link_pages')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('max_clicks')->nullable();
            $table->unsignedBigInteger('click_count')->default(0);
            $table->unsignedInteger('revision_version')->default(1);
            $table->boolean('approval_required')->default(false);
            $table->string('approval_status', 16)->default('approved');
            $table->json('utm_parameters')->nullable();
            $table->json('custom_parameters')->nullable();
            $table->index(['workspace_id', 'archived_at']);
            $table->index(['workspace_id', 'approval_status']);
        });

        // Preserve the existing behavior where an assigned active overlay is
        // shown before a direct destination.
        DB::table('links')->whereNotNull('link_overlay_id')->update(['delivery_mode' => 'overlay']);
        DB::statement('UPDATE links SET click_count = (SELECT COUNT(*) FROM clicks WHERE clicks.link_id = links.id)');
        DB::table('links')->whereNull('approval_status')->update(['approval_status' => 'approved']);
    }

    public function down(): void
    {
        $requiresNewContract = DB::table('links')
            ->where(function ($query): void {
                $query->whereNull('destination_url')
                    ->orWhereNotNull('link_page_id')
                    ->orWhereNotNull('archived_at')
                    ->orWhereNotNull('max_clicks')
                    ->orWhere('approval_required', true)
                    ->orWhere('approval_status', '!=', 'approved')
                    ->orWhereNotNull('utm_parameters')
                    ->orWhereNotNull('custom_parameters')
                    ->orWhereNotIn('delivery_mode', ['direct', 'overlay'])
                    ->orWhere('revision_version', '!=', 1);
            })
            ->exists();

        if ($requiresNewContract) {
            throw new LogicException('Cannot remove advanced link fields while they contain active campaign data.');
        }

        Schema::table('links', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'archived_at']);
            $table->dropIndex(['workspace_id', 'approval_status']);
            $table->dropConstrainedForeignId('link_page_id');
            $table->dropColumn([
                'delivery_mode',
                'archived_at',
                'max_clicks',
                'approval_required',
                'approval_status',
                'utm_parameters',
                'custom_parameters',
                'click_count',
                'revision_version',
            ]);
            $table->string('destination_url', 2048)->nullable(false)->change();
        });
    }
};
