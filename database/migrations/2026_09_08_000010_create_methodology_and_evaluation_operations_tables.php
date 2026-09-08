<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_version_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->decimal('weight', 5, 2)->default(0);
            $table->boolean('is_mandatory')->default(false);
            $table->json('applicability_rules')->nullable();
            $table->json('scoring_rules')->nullable();
            $table->timestamps();
            $table->unique(['standard_version_id', 'code']);
        });

        Schema::create('criterion_guidance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->json('evidence_expectations')->nullable();
            $table->json('scoring_anchors')->nullable();
            $table->timestamps();
        });

        Schema::create('auditor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('auditor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('offered');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('compensation_amount_minor')->nullable();
            $table->char('compensation_currency', 3)->nullable();
            $table->string('compensation_status')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'auditor_id']);
            $table->unique(['evaluation_id', 'sequence']);
        });

        Schema::create('conflict_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('auditor_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('declaration_type');
            $table->text('disclosure')->nullable();
            $table->string('outcome')->default('potential_conflict');
            $table->foreignId('determined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('determined_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auditor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('auditor_assignment_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->unique(['auditor_assignment_id', 'version']);
        });

        Schema::create('criterion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auditor_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained()->restrictOnDelete();
            $table->string('assessment');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('rationale')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['auditor_evaluation_id', 'criterion_id']);
        });

        Schema::create('criterion_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('criterion_id')->constrained()->restrictOnDelete();
            $table->foreignId('criterion_result_id')->constrained()->restrictOnDelete();
            $table->foreignId('auditor_id')->constrained('users')->restrictOnDelete();
            $table->string('decision');
            $table->timestamps();
            $table->unique(['evaluation_id', 'criterion_id', 'auditor_id']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('criterion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('auditor_evaluation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('severity')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->foreignId('auditor_evaluation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('criterion_result_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('source_url')->nullable();
            $table->text('storage_path')->nullable();
            $table->text('provenance')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->string('visibility')->default('private');
            $table->timestamps();
        });

        Schema::create('evaluation_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->string('decision');
            $table->text('rationale');
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->foreignId('supersedes_decision_id')->nullable()->constrained('evaluation_decisions')->nullOnDelete();
            $table->timestamps();
            $table->unique(['evaluation_id', 'decided_at']);
        });

        Schema::create('validation_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('validation_id')->constrained()->cascadeOnDelete();
            $table->string('verification_identifier')->unique();
            $table->string('status');
            $table->timestamp('issued_at');
            $table->string('embed_version')->default('1');
            $table->timestamps();
        });

        Schema::create('public_verification_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('validation_id')->constrained()->cascadeOnDelete();
            $table->string('public_slug')->unique();
            $table->boolean('directory_visible')->default(true);
            $table->boolean('full_report_visible')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_version_id')->nullable();
            $table->timestamp('creator_visible_at')->nullable();
            $table->timestamp('public_visible_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('content_structure');
            $table->text('abstract')->nullable();
            $table->json('decision_snapshot')->nullable();
            $table->json('standard_version_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'version_number']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('report_versions')
                ->nullOnDelete();
        });

        Schema::create('clarification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('message');
            $table->string('status')->default('open');
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('grounds');
            $table->string('status')->default('open');
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('outcome')->nullable();
            $table->text('decision_rationale')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('clarification_requests');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('report_versions');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('public_verification_records');
        Schema::dropIfExists('validation_badges');
        Schema::dropIfExists('evaluation_decisions');
        Schema::dropIfExists('evidence');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('criterion_votes');
        Schema::dropIfExists('criterion_results');
        Schema::dropIfExists('auditor_evaluations');
        Schema::dropIfExists('conflict_declarations');
        Schema::dropIfExists('auditor_assignments');
        Schema::dropIfExists('criterion_guidance');
        Schema::dropIfExists('criteria');
    }
};
