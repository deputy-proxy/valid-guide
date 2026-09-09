<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditor_profile_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['auditor_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditor_profile_reviews');
    }
};
