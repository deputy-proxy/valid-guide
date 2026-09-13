<?php

declare(strict_types=1);

use App\Enums\MarketplaceServiceStatus;
use App\Enums\MarketplaceTransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_profile_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->json('expertise_areas')->nullable();
            $table->json('product_types')->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3);
            $table->string('status')->default(MarketplaceServiceStatus::Draft->value)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'title']);
            $table->index(['auditor_profile_id', 'status']);
        });

        Schema::create('marketplace_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketplace_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('auditor_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status')->default(MarketplaceTransactionStatus::Pending->value)->index();
            $table->string('provider')->nullable();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->text('cancellation_reason')->nullable();
            $table->text('refund_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['organization_id', 'status']);
            $table->index(['auditor_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_transactions');
        Schema::dropIfExists('marketplace_services');
    }
};
