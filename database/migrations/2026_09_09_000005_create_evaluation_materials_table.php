<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('location')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->index(['evaluation_request_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_materials');
    }
};
