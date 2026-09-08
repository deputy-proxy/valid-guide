<?php

use App\Enums\ValidationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_release_id')->constrained()->restrictOnDelete();
            $table->foreignId('evaluation_id')->constrained()->restrictOnDelete();
            $table->string('verification_identifier')->unique();
            $table->timestamp('issued_at');
            $table->string('status')->default(ValidationStatus::Active->value)->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamps();
            $table->unique(['product_release_id', 'evaluation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validations');
    }
};
