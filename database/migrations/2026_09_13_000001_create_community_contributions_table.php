<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('body');
            $table->string('status')->index();
            $table->text('moderation_reason')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_contributions');
    }
};
