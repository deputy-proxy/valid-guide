<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_guidances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('report_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('report_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('criterion_result_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 50);
            $table->string('title');
            $table->text('guidance');
            $table->text('rationale');
            $table->string('priority', 20);
            $table->json('applicability')->nullable();
            $table->boolean('requires_action')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestamp('creator_visible_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['evaluation_id', 'category']);
            $table->index('creator_visible_at');
            $table->unique(['evaluation_id', 'finding_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_guidances');
    }
};
