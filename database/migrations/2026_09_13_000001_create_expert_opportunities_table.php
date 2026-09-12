<?php

declare(strict_types=1);

use App\Enums\ExpertOpportunityStatus;
use App\Enums\ExpertOpportunityType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('type')->default(ExpertOpportunityType::Review->value)->index();
            $table->json('expertise_areas')->nullable();
            $table->json('product_types')->nullable();
            $table->string('workload')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('application_deadline')->nullable();
            $table->json('eligibility_constraints')->nullable();
            $table->string('status')->default(ExpertOpportunityStatus::Draft->value)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_opportunities');
    }
};
