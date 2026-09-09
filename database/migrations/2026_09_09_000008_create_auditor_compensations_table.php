<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditor_compensations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_assignment_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status')->default('pending')->index();
            $table->timestamp('payable_at')->nullable();
            $table->timestamp('forfeited_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditor_compensations');
    }
};
