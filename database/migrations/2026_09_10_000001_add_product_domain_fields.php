<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('canonical_url')->nullable()->after('description');
            $table->string('language', 16)->nullable()->after('claimed_outcomes');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['products_organization_id_status_index']);
            $table->dropColumn(['canonical_url', 'language']);
        });
    }
};
