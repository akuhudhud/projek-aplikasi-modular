<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_license_verifications', function (Blueprint $table) {
            $table->string('license_type', 30)
                ->default('GENERAL')
                ->after('license_number_encrypted');

            $table->uuid('previous_license_verification_id')
                ->nullable()
                ->after('license_type');

            $table->index(
                ['account_id', 'license_type', 'license_class', 'status'],
                'runner_license_type_class_status_index'
            );

            $table->index(
                'previous_license_verification_id',
                'runner_license_previous_index'
            );

            $table->foreign(
                'previous_license_verification_id',
                'runner_license_prev_fk'
            )
                ->references('id')
                ->on('runner_license_verifications')
                ->restrictOnDelete();
        });

        DB::table('runner_license_verifications')
            ->whereIn('license_class', ['B', 'B1', 'B2'])
            ->update(['license_type' => 'MOTORCYCLE']);

        DB::table('runner_license_verifications')
            ->whereIn('license_class', ['D', 'DA'])
            ->update(['license_type' => 'CAR']);

        DB::table('runner_license_verifications')
            ->where('license_class', 'PSV')
            ->update(['license_type' => 'PSV']);

        DB::table('runner_license_verifications')
            ->whereIn('license_class', ['E', 'EA', 'EB', 'EC'])
            ->update(['license_type' => 'E']);
    }

    public function down(): void
    {
        Schema::table('runner_license_verifications', function (Blueprint $table) {
            $table->dropForeign('runner_license_prev_fk');

            $table->dropIndex('runner_license_previous_index');

            $table->dropIndex(
                'runner_license_type_class_status_index'
            );

            $table->dropColumn([
                'previous_license_verification_id',
                'license_type',
            ]);
        });
    }
};
