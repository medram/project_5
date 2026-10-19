<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->unsignedBigInteger('monthly_price');
            $table->string('currency', 3);
            $table->unsignedInteger('link_limit')->nullable();
            $table->unsignedInteger('domain_limit')->nullable();
            $table->unsignedInteger('pixel_limit')->nullable();
            $table->unsignedInteger('webhook_limit')->nullable();
            $table->unsignedInteger('link_page_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('source', 20);
            $table->timestamps();
        });

        $now = now();

        DB::table('plans')->insert([
            [
                'code' => 'free',
                'name' => 'Free',
                'monthly_price' => 0,
                'currency' => 'USD',
                'link_limit' => 25,
                'domain_limit' => 0,
                'pixel_limit' => 0,
                'webhook_limit' => 0,
                'link_page_limit' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'monthly_price' => 2900,
                'currency' => 'USD',
                'link_limit' => 500,
                'domain_limit' => 3,
                'pixel_limit' => 10,
                'webhook_limit' => 5,
                'link_page_limit' => 25,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'business',
                'name' => 'Business',
                'monthly_price' => 9900,
                'currency' => 'USD',
                'link_limit' => null,
                'domain_limit' => null,
                'pixel_limit' => null,
                'webhook_limit' => null,
                'link_page_limit' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('plans');
    }
};
