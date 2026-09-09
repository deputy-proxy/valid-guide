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
            $table->json('score_anchors')->nullable();
            $table->json('decision_thresholds')->nullable();
        });

        $anchors = json_encode([
            'exceeds' => ['min' => 90, 'max' => 100],
            'meets' => ['min' => 75, 'max' => 89],
            'partially_meets' => ['min' => 50, 'max' => 74],
            'does_not_meet' => ['min' => 0, 'max' => 49],
            'insufficient_evidence' => ['min' => null, 'max' => null],
            'not_applicable' => ['min' => null, 'max' => null],
        ], JSON_THROW_ON_ERROR);

        $thresholds = json_encode([
            'overall_minimum' => 75,
            'mandatory_minimum' => 75,
            'dimension_minimum' => 60,
        ], JSON_THROW_ON_ERROR);

        DB::table('standard_versions')->update([
            'score_anchors' => $anchors,
            'decision_thresholds' => $thresholds,
        ]);
    }

    public function down(): void
    {
        Schema::table('standard_versions', function (Blueprint $table): void {
            $table->dropColumn(['score_anchors', 'decision_thresholds']);
        });
    }
};
