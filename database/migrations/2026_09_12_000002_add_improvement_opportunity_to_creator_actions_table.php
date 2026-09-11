<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_actions', function (Blueprint $table) {
            $table->foreignId('improvement_opportunity_id')
                ->nullable()
                ->after('finding_id')
                ->constrained('improvement_opportunities')
                ->nullOnDelete();

            $table->index('improvement_opportunity_id');
        });
    }

    public function down(): void
    {
        Schema::table('creator_actions', function (Blueprint $table) {
            $table->dropForeign(['improvement_opportunity_id']);
            $table->dropIndex(['improvement_opportunity_id']);
            $table->dropColumn('improvement_opportunity_id');
        });
    }
};
