<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_directory_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_verification_record_id')->unique()->constrained('public_verification_records');
            $table->string('verification_identifier', 100)->unique();
            $table->string('title');
            $table->string('slug');
            $table->string('creator_name');
            $table->string('product_type');
            $table->string('subject_area')->nullable();
            $table->string('language')->nullable();
            $table->json('matching_audiences')->nullable();
            $table->json('matching_goals')->nullable();
            $table->string('validation_status');
            $table->string('release_identifier');
            $table->timestamp('issued_at')->nullable();
            $table->boolean('directory_visible')->default(true);
            $table->timestamps();

            $table->index(['directory_visible', 'validation_status']);
            $table->index('product_type');
            $table->index('subject_area');
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_directory_entries');
    }
};
