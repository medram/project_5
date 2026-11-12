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
            $table->foreignId('avatar_media_id')
                ->nullable()
                ->after('email_verified_at')
                ->constrained('content_media')
                ->nullOnDelete();
            $table->string('locale', 12)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('purge_after')->nullable()->index();
        });

        Schema::table('content_media', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->change();
        });

        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_user_id', 191);
            $table->string('email', 255)->nullable();
            $table->boolean('email_verified')->default(false);
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');

        DB::table('content_media')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(365)]);

        Schema::table('content_media', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('avatar_media_id');
            $table->dropColumn([
                'locale',
                'country_code',
                'timezone',
                'last_seen_at',
                'deletion_requested_at',
                'purge_after',
            ]);
        });
    }
};
