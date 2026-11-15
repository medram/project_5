<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('remember_token');
        });

        $superAdminId = DB::table('platform_roles')->where('code', 'super_admin')->value('id');

        if ($superAdminId !== null) {
            DB::table('users')
                ->whereIn('id', DB::table('platform_role_user')->select('user_id')->where('platform_role_id', $superAdminId))
                ->update(['is_admin' => true]);
        }
    }
};
