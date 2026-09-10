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
            ->join('product_releases', 'product_releases.id', '=', 'evaluations.product_release_id')
            ->whereNull('evaluations.product_id')
            ->update([
                'evaluations.product_id' => DB::raw('product_releases.product_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'submitted_at', 'internal_reviewed_at']);
        });
    }
};
