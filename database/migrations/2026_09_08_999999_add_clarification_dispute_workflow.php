<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clarification_requests', function (Blueprint $table): void {
            $table->foreignId('submitted_by')->nullable()->after('organization_id')->constrained('users')->restrictOnDelete();
            $table->text('response')->nullable()->after('message');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->restrictOnDelete();
        });

        Schema::table('disputes', function (Blueprint $table): void {
            $table->foreignId('submitted_by')->nullable()->after('organization_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->restrictOnDelete();
        });

        Schema::create('dispute_reviewers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('assigned')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['dispute_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_reviewers');

        Schema::table('disputes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('resolved_by');
        });

        Schema::table('clarification_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('response');
            $table->dropConstrainedForeignId('resolved_by');
        });
    }
};
