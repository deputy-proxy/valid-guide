<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_directory_entries', function (Blueprint $table): void {
            $table->dropIndex(['directory_visible', 'validation_status']);
            $table->index(
                ['directory_visible', 'validation_status', 'title', 'verification_identifier'],
                'public_directory_entries_visibility_status_order_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('public_directory_entries', function (Blueprint $table): void {
            $table->dropIndex('public_directory_entries_visibility_status_order_index');
            $table->index(['directory_visible', 'validation_status']);
        });
    }
};
