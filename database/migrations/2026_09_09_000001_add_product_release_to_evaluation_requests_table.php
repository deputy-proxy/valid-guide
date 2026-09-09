<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table): void {
            $table->foreignId('product_release_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_releases')
                ->restrictOnDelete();

            $table->index(['product_release_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table): void {
            $table->dropForeign(['product_release_id']);
            $table->dropIndex(['product_release_id', 'status']);
            $table->dropColumn('product_release_id');
        });
    }
};
