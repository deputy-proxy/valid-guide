<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditor_annual_conflict_declarations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->text('disclosure')->nullable();
            $table->string('outcome')->default('potential_conflict');
            $table->timestamp('submitted_at');
            $table->foreignId('determined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('determined_at')->nullable();
            $table->timestamps();
            $table->unique(['auditor_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditor_annual_conflict_declarations');
    }
};
