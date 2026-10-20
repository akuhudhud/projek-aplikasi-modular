<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('runner_license_verifications')
            ->where('license_type', 'MOTORCYCLE')
            ->whereIn('license_class', ['B', 'B1', 'B2'])
            ->update(['license_type' => 'GENERAL']);

        DB::table('runner_license_verifications')
            ->where('license_type', 'CAR')
            ->whereIn('license_class', ['D', 'DA'])
            ->update(['license_type' => 'GENERAL']);

        DB::table('runner_license_verifications')
            ->where('license_type', 'PSV')
            ->where('license_class', 'PSV')
            ->update(['license_type' => 'GENERAL']);

        DB::table('runner_license_verifications')
            ->where('license_type', 'E')
            ->whereIn('license_class', ['E', 'EA', 'EB', 'EC'])
            ->update(['license_type' => 'GENERAL']);
    }

    public function down(): void
    {
        // Pemetaan hardcode sengaja tidak dipulihkan.
    }
};
