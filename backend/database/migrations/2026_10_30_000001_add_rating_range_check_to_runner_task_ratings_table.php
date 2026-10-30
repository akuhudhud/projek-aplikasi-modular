<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT_NAME =
        'runner_task_ratings_rating_range_check';

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE runner_task_ratings
             ADD CONSTRAINT '.self::CONSTRAINT_NAME.'
             CHECK (rating BETWEEN 0 AND 5)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE runner_task_ratings
             DROP CHECK '.self::CONSTRAINT_NAME
        );
    }
};
