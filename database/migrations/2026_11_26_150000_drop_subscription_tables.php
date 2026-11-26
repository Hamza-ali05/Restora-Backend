<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove subscription/payment tables. support_messages remains.
     */
    public function up(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }

    public function down(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tenant_subscription_id')->constrained('tenant_subscriptions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('status')->default('paid');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
};
