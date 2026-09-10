<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_releases')
            ->where('status', 'available')
            ->update(['status' => 'current']);
    }

    public function down(): void
    {
        DB::table('product_releases')
            ->where('status', 'current')
            ->update(['status' => 'available']);
    }
};
