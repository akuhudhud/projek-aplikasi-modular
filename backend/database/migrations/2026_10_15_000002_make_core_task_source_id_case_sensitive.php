<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE core_tasks
             MODIFY source_id VARCHAR(191)
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_bin
             NOT NULL'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'ALTER TABLE core_tasks
             MODIFY source_id VARCHAR(191)
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci
             NOT NULL'
        );
    }
};
