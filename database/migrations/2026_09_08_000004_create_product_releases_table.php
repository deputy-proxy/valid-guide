<?php

use App\Enums\ProductReleaseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('edition')->nullable();
            $table->string('version')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('release_identifier');
            $table->string('product_url_snapshot')->nullable();
            $table->string('title_snapshot');
            $table->json('quantitative_metadata')->nullable();
            $table->text('material_change_notes')->nullable();
            $table->string('status')->default(ProductReleaseStatus::Draft->value)->index();
            $table->timestamps();
            $table->unique(['product_id', 'release_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_releases');
    }
};
