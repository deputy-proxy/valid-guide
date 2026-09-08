<?php

use App\Enums\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('product_type')->default(ProductType::Course->value)->index();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->decimal('reference_price', 12, 2)->nullable();
            $table->char('reference_currency', 3)->nullable();
            $table->text('target_audience')->nullable();
            $table->json('claimed_outcomes')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
