<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criteria', function (Blueprint $table): void {
            $table->string('voting_mode')->default('individual')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('criteria', function (Blueprint $table): void {
            $table->dropColumn('voting_mode');
        });
    }
};
