<?php

declare(strict_types=1);

use App\Enums\CreatorActionPriority;
use App\Enums\ImprovementOpportunityStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('improvement_guidance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('superseded_by_id')->nullable()->constrained('improvement_opportunities')->nullOnDelete();
            $table->string('title');
            $table->text('target_outcome');
            $table->text('evidence_required');
            $table->text('completion_evidence')->nullable();
            $table->string('priority')->default(CreatorActionPriority::Medium->value);
            $table->string('status')->default(ImprovementOpportunityStatus::Open->value);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'finding_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_opportunities');
    }
};
