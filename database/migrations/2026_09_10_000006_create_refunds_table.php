<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('evaluation_request_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3);
            $table->string('status', 32)->index();
            $table->string('provider', 32);
            $table->string('provider_refund_id')->nullable()->unique();
            $table->string('reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();

            $table->unique('payment_id');
            $table->index(['evaluation_request_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
