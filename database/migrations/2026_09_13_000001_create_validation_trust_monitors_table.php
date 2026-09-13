<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validation_trust_monitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('validation_id')->constrained()->restrictOnDelete();
            $table->string('cadence');
            $table->string('status');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at');
            $table->string('observed_fingerprint', 64)->nullable();
            $table->string('failure_fingerprint', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('validation_id');
            $table->index(['status', 'next_check_at']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('validation_trust_monitor_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('validation_trust_monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('fingerprint', 64);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');

            $table->unique(['validation_trust_monitor_id', 'type', 'fingerprint'], 'validation_monitor_event_dedup');
            $table->index(['organization_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validation_trust_monitor_events');
        Schema::dropIfExists('validation_trust_monitors');
    }
};
