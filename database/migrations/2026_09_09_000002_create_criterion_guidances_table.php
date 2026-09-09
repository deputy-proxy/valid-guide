<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterion_guidances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('criterion_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->json('evidence_expectations')->nullable();
            $table->json('scoring_anchors')->nullable();
            $table->timestamps();
            $table->unique(['criterion_id', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterion_guidances');
    }
};
