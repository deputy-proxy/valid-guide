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
        Schema::table('service_packages', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_minor')->nullable()->after('price');
        });

        DB::table('service_packages')->update([
            'price_minor' => DB::raw('CAST(ROUND(price * 100) AS INTEGER)'),
        ]);

        Schema::table('service_packages', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_minor')->nullable(false)->change();
        });

        Schema::table('evaluation_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('quoted_amount_minor')->nullable()->after('quoted_price');
        });

        DB::table('evaluation_requests')->update([
            'quoted_amount_minor' => DB::raw('CASE WHEN quoted_price IS NULL THEN NULL ELSE CAST(ROUND(quoted_price * 100) AS INTEGER) END'),
        ]);
    }

    public function down(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table): void {
            $table->dropColumn('quoted_amount_minor');
        });

        Schema::table('service_packages', function (Blueprint $table): void {
            $table->dropColumn('price_minor');
        });
    }
};
