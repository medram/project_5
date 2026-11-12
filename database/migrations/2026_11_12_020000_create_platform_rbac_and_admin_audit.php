<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->timestamps();
        });

        Schema::create('platform_role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['platform_role_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('permission', 80);
            $table->string('action', 80);
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('reason', 500)->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        $now = now();

        DB::table('platform_roles')->insert([
            [
                'code' => 'super_admin',
                'name' => 'Super administrator',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'support',
                'name' => 'Support',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $superAdminId = DB::table('platform_roles')->where('code', 'super_admin')->value('id');

        DB::table('users')
            ->where('is_admin', true)
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($superAdminId): void {
                foreach ($users as $user) {
                    DB::table('platform_role_user')->insertOrIgnore([
                        'platform_role_id' => $superAdminId,
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('platform_role_user');
        Schema::dropIfExists('platform_roles');
    }
};
