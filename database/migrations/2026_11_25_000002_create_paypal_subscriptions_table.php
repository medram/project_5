<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paypal_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_paypal_price_id')->constrained()->restrictOnDelete();
            $table->uuid('custom_id')->unique();
            $providerId = $table->string('paypal_subscription_id')->nullable();
            if (DB::getDriverName() === 'mysql') {
                $providerId->collation('utf8mb4_bin');
            }
            $providerId->unique();
            $table->string('status');
            $table->timestamp('last_paid_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('payment_checkout_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('provider');
            $table->string('interval');
            $table->uuid('reference')->unique();
            $table->string('provider_id');
            $table->text('approval_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('paypal_subscriptions')->exists() || DB::table('payment_checkout_attempts')->exists()) {
            throw new RuntimeException('Cannot roll back PayPal billing while subscription or checkout history exists.');
        }

        Schema::dropIfExists('payment_checkout_attempts');
        Schema::dropIfExists('paypal_subscriptions');
    }
};
