<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->foreignId('service_package_id')->nullable()->after('product_release_id')->constrained('service_packages')->restrictOnDelete();
            $table->string('service_package_name_snapshot')->nullable()->after('service_package_id');
            $table->text('service_package_description_snapshot')->nullable()->after('service_package_name_snapshot');
            $table->json('service_package_terms_snapshot')->nullable()->after('service_package_description_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->dropForeign(['service_package_id']);
            $table->dropColumn([
                'service_package_id',
                'service_package_name_snapshot',
                'service_package_description_snapshot',
                'service_package_terms_snapshot',
            ]);
        });
    }
};
