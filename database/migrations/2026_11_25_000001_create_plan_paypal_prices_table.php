<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('paypal_monthly_plan_id')->nullable();
            $table->string('paypal_yearly_plan_id')->nullable();
        });
        Schema::create('plan_paypal_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $id = $table->string('paypal_plan_id');
            if (DB::getDriverName() === 'mysql') {
                $id->collation('utf8mb4_bin');
            }
            $id->unique();
            $table->string('interval');
            $table->string('currency', 3);
            $table->unsignedBigInteger('unit_amount');
            $table->timestamp('verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('plan_paypal_prices')->exists() || DB::table('plans')->whereNotNull('paypal_monthly_plan_id')->orWhereNotNull('paypal_yearly_plan_id')->exists()) {
            throw new RuntimeException('Cannot roll back PayPal plan mappings while mapping data exists.');
        }
        Schema::dropIfExists('plan_paypal_prices');
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['paypal_monthly_plan_id', 'paypal_yearly_plan_id']);
        });
    }
};
