<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_public_profiles', function (Blueprint $table) {
            $table->string('base_location', 120)
                ->nullable()
                ->after('service_area');

            $table->string('coverage_area', 500)
                ->nullable()
                ->after('base_location');
        });
    }

    public function down(): void
    {
        Schema::table('runner_public_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'base_location',
                'coverage_area',
            ]);
        });
    }
};
