<?php

use App\Enums\EvaluationRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('service_package')->default('validation');
            $table->string('complexity')->default('standard');
            $table->decimal('quoted_price', 12, 2);
            $table->char('currency', 3)->default('EUR');
            $table->string('status')->default(EvaluationRequestStatus::Draft->value)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('payment_started_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('evaluation_started_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('intake_notes')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_requests');
    }
};
