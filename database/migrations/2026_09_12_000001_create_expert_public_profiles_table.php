<?php

declare(strict_types=1);

use App\Enums\ExpertPublicProfileStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_public_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('auditor_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->text('bio')->nullable();
            $table->text('credentials')->nullable();
            $table->json('expertise_areas')->nullable();
            $table->json('product_types')->nullable();
            $table->string('status')->default(ExpertPublicProfileStatus::Draft->value)->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'display_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_public_profiles');
    }
};
