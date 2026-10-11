<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve historical audit records when an account is deleted.
     *
     * Keep actor_account_id and target_account_id as historical UUIDs,
     * without cascading foreign keys to the accounts table.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(
                'audit_logs_actor_account_id_foreign'
            );

            $table->dropForeign(
                'audit_logs_target_account_id_foreign'
            );
        });
    }

    /**
     * Restore the original foreign keys only when doing so will not
     * invalidate historical audit records.
     */
    public function down(): void
    {
        $hasMissingActor = DB::table('audit_logs as logs')
            ->leftJoin(
                'accounts as actors',
                'actors.id',
                '=',
                'logs.actor_account_id'
            )
            ->whereNull('actors.id')
            ->exists();

        $hasMissingTarget = DB::table('audit_logs as logs')
            ->leftJoin(
                'accounts as targets',
                'targets.id',
                '=',
                'logs.target_account_id'
            )
            ->whereNull('targets.id')
            ->exists();

        if ($hasMissingActor || $hasMissingTarget) {
            throw new RuntimeException(
                'Cannot restore audit log foreign keys because '
                .'historical audit records reference accounts '
                .'that no longer exist. Keep the retention migration '
                .'applied to preserve audit history.'
            );
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('actor_account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();

            $table->foreign('target_account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();
        });
    }
};
