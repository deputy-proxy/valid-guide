<?php

use App\Enums\EvaluationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_release_id')->constrained()->restrictOnDelete();
            $table->foreignId('standard_version_id')->constrained()->restrictOnDelete();
            $table->string('status')->default(EvaluationStatus::Pending->value)->index();
            $table->string('decision')->nullable()->index();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('decision_rationale')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
