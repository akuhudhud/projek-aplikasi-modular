<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_license_verifications', function (Blueprint $table) {
            $table->string('license_type', 30)
                ->after('license_number');

            $table->index(
                ['account_id', 'license_type', 'license_class', 'status'],
                'runner_license_type_class_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('runner_license_verifications', function (Blueprint $table) {
            $table->dropIndex(
                'runner_license_type_class_status_index'
            );

            $table->dropColumn('license_type');
        });
    }
};
