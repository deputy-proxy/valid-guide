<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_verification_records', function (Blueprint $table) {
            $table->json('snapshot')->nullable()->after('public_slug');
            $table->unique('validation_id');
        });
    }

    public function down(): void
    {
        Schema::table('public_verification_records', function (Blueprint $table) {
            $table->dropUnique(['validation_id']);
            $table->dropColumn('snapshot');
        });
    }
};
