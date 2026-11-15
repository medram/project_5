<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 80);
            $table->string('direction', 3)->default('ltr');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('locale_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            $table->text('value');
            $table->timestamps();

            $table->unique(['locale_id', 'key']);
        });

        DB::table('locales')->insert([
            'code' => 'en',
            'name' => 'English',
            'direction' => 'ltr',
            'is_published' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('locales');
    }
};
