<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standard_versions', function (Blueprint $table): void {
            $table->json('auditor_staffing_rules')->nullable();
        });

        $rules = json_encode([
            'simple' => 1,
            'standard' => 1,
            'complex' => 3,
            'exceptional' => 5,
        ], JSON_THROW_ON_ERROR);

        DB::table('standard_versions')->update([
            'auditor_staffing_rules' => $rules,
        ]);
    }

    public function down(): void
    {
        Schema::table('standard_versions', function (Blueprint $table): void {
            $table->dropColumn('auditor_staffing_rules');
        });
    }
};
