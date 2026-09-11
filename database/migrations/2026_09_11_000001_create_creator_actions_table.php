<?php

declare(strict_types=1);

use App\Enums\CreatorActionPriority;
use App\Enums\CreatorActionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('priority')->default(CreatorActionPriority::Medium->value);
            $table->string('status')->default(CreatorActionStatus::Pending->value);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'finding_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_actions');
    }
};
