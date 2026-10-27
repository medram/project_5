<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('qr_campaign_limit')->nullable();
        });
        Schema::create('qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('linked_qr_link_id')->nullable()->unique()->constrained('links')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 24)->unique();
            $table->string('source', 12);
            $table->string('title', 160)->nullable();
            $table->string('destination_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('max_scans')->nullable();
            $table->unsignedBigInteger('scan_count')->default(0);
            $table->string('access_code')->nullable();
            $table->unsignedTinyInteger('access_code_version')->default(1);
            $table->string('foreground_color', 7)->default('#000000');
            $table->string('background_color', 7)->default('#FFFFFF');
            $table->string('error_correction', 12)->default('medium');
            $table->unsignedTinyInteger('margin')->default(4);
            $table->unsignedSmallInteger('size')->default(320);
            $table->string('logo_path')->nullable();
            $table->string('logo_alt', 160)->nullable();
            $table->string('share_title', 160)->nullable();
            $table->text('share_description')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'source']);
        });

        Schema::create('qr_scans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('qr_code_id')->constrained('qr_codes')->cascadeOnDelete();
            $table->timestamp('scanned_at');
            $table->index(['qr_code_id', 'scanned_at']);
        });

        $now = now();
        foreach (DB::table('links')->select(['id', 'workspace_id', 'title', 'slug'])->orderBy('id')->cursor() as $link) {
            DB::table('qr_codes')->insert([
                'workspace_id' => $link->workspace_id,
                'linked_qr_link_id' => $link->id,
                'link_id' => $link->id,
                'code' => $this->uniqueCode(),
                'source' => 'linked',
                'title' => $link->title ?: $link->slug,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('qr_codes') && (DB::table('qr_codes')->where('source', 'campaign')->exists() || DB::table('qr_scans')->exists())) {
            throw new RuntimeException('Cannot remove QR campaign data while campaign QR codes or scans exist.');
        }

        Schema::dropIfExists('qr_scans');
        Schema::dropIfExists('qr_codes');
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('qr_campaign_limit');
        });
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(12));
        } while (DB::table('qr_codes')->where('code', $code)->exists());

        return $code;
    }
};
