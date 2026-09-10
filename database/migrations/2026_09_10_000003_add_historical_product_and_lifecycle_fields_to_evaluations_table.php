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
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->foreignId('product_id')->nullable()->after('evaluation_request_id')->constrained()->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('started_at');
            $table->timestamp('internal_reviewed_at')->nullable()->after('submitted_at');
        });

        DB::table('evaluations')
            ->select(['id', 'product_release_id'])
            ->whereNull('product_id')
            ->orderBy('id')
            ->eachById(function (object $evaluation): void {
                $productId = DB::table('product_releases')
                    ->where('id', $evaluation->product_release_id)
                    ->value('product_id');

                if ($productId !== null) {
                    DB::table('evaluations')
                        ->where('id', $evaluation->id)
                        ->update(['product_id' => $productId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'submitted_at', 'internal_reviewed_at']);
        });
    }
};
