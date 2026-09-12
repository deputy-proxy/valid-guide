<?php

declare(strict_types=1);

use App\Enums\ExpertOpportunityParticipationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_opportunity_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expert_opportunity_id')->constrained()->restrictOnDelete();
            $table->foreignId('auditor_profile_id')->constrained()->restrictOnDelete();
            $table->string('status')->default(ExpertOpportunityParticipationStatus::Applied->value)->index();
            $table->text('application_note')->nullable();
            $table->text('conflict_disclosure');
            $table->string('conflict_outcome')->nullable()->index();
            $table->foreignId('conflict_determined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('conflict_determined_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('selected_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['expert_opportunity_id', 'auditor_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_opportunity_participations');
    }
};
