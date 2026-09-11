<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_actions', function (Blueprint $table): void {
            $table->foreignId('improvement_guidance_id')
                ->nullable()
                ->after('finding_id')
                ->constrained('improvement_guidances')
                ->nullOnDelete();
            $table->unique('improvement_guidance_id');
        });
    }

    public function down(): void
    {
        Schema::table('creator_actions', function (Blueprint $table): void {
            $table->dropForeign(['improvement_guidance_id']);
            $table->dropUnique(['improvement_guidance_id']);
            $table->dropColumn('improvement_guidance_id');
        });
    }
};
