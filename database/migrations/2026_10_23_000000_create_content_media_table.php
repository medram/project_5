<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('disk', 100);
            $table->string('path', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->string('visibility', 20)->default('private');
            $table->string('scan_state', 20)->default('not_required');
            $table->timestamp('expires_at');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['workspace_id', 'expires_at']);
            $table->index(['deleted_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('content_media') && DB::table('content_media')->exists()) {
            throw new LogicException('Cannot remove content media metadata while stored objects may exist.');
        }

        Schema::dropIfExists('content_media');
    }
};
