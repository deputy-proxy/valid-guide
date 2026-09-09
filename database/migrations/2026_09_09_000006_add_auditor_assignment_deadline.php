<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditor_assignments', function (Blueprint $table): void {
            $table->timestamp('due_at')->nullable()->after('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('auditor_assignments', function (Blueprint $table): void {
            $table->dropColumn('due_at');
        });
    }
};
