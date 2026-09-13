<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('price_minor');
            $table->string('currency', 3);
            $table->string('billing_interval', 16);
            $table->unsignedSmallInteger('billing_interval_count')->default(1);
            $table->timestamps();
        });

        Schema::create('subscription_plan_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'code']);
            $table->index(['subscription_plan_id', 'sort_order']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 32)->index();
            $table->string('provider', 32)->nullable();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('plan_code_snapshot', 64);
            $table->string('plan_name_snapshot', 255);
            $table->unsignedBigInteger('price_minor_snapshot');
            $table->string('currency_snapshot', 3);
            $table->string('billing_interval_snapshot', 16);
            $table->unsignedSmallInteger('billing_interval_count_snapshot');
            $table->json('entitlements_snapshot');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 2000)->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'current_period_end']);
        });

        Schema::create('subscription_billing_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status', 32)->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('provider', 32)->nullable();
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('failure_reason', 2000)->nullable();
            $table->string('refund_reason', 2000)->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'sequence']);
            $table->unique('provider_payment_id');
            $table->index(['subscription_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_billing_records');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plan_entitlements');
        Schema::dropIfExists('subscription_plans');
    }
};
