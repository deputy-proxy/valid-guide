<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('status')->default('pending')->index();
            $table->boolean('methodology_literate')->default(false);
            $table->json('format_experience')->nullable();
            $table->text('bio')->nullable();
            $table->text('credentials')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('auditor_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('experience_type');
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->text('evidence')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['auditor_profile_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditor_competencies');
        Schema::dropIfExists('auditor_profiles');
    }
};
