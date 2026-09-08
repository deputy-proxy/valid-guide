<?php

use App\Enums\StandardVersionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_standard_id')->constrained()->restrictOnDelete();
            $table->string('version');
            $table->text('description')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->string('status')->default(StandardVersionStatus::Draft->value)->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_standard_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_versions');
    }
};
