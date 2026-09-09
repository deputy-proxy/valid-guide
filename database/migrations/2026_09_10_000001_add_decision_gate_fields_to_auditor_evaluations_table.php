<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditor_evaluations', function (Blueprint $table): void {
            $table->string('evidence_sufficiency')->nullable()->after('locked_at');
            $table->string('audience_promise_coherence')->nullable()->after('evidence_sufficiency');
        });
    }

    public function down(): void
    {
        Schema::table('auditor_evaluations', function (Blueprint $table): void {
            $table->dropColumn(['evidence_sufficiency', 'audience_promise_coherence']);
        });
    }
};
