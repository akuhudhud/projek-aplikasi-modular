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
            $table->boolean('is_current')
                ->default(false)
                ->after('status');

            $table->dropUnique(['account_id']);

            $table->index(
                ['account_id', 'is_current'],
                'runner_license_account_current_index'
            );
        });

        DB::table('runner_license_verifications')
            ->where('status', 'VERIFIED')
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->update(['is_current' => true]);
    }

    public function down(): void
    {
        $hasDuplicateAccounts = DB::table('runner_license_verifications')
            ->select('account_id')
            ->groupBy('account_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateAccounts) {
            throw new RuntimeException(
                'Cannot roll back runner license history migration '
                .'while multiple license records exist for an account.'
            );
        }

        Schema::table('runner_license_verifications', function (Blueprint $table) {
            $table->dropIndex('runner_license_account_current_index');
            $table->dropColumn('is_current');
            $table->unique('account_id');
        });
    }
};
