<?php

use App\Enums\PlatformRoleCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->string('permission', 80);
            $table->unique(['platform_role_id', 'permission']);
        });
    }

    public function down(): void
    {
        if (DB::table('platform_roles')->whereNotIn('code', array_column(PlatformRoleCode::cases(), 'value'))->exists()) {
            throw new RuntimeException('Cannot roll back role permissions while custom platform roles exist.');
        }

        Schema::dropIfExists('platform_role_permissions');
    }
};
