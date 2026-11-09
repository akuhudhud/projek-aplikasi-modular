<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT_NAME =
        'runner_task_ratings_rating_range_check';

    public function up(): void
    {
        Schema::create('runner_task_ratings', function (Blueprint $table) {
            $table->uuid('rating_id')->primary();

            // Satu penilaian sahaja bagi setiap Core Task.
            $table->uuid('task_id')->unique();

            // Identiti dalaman untuk authorization.
            // Jangan dedahkan customer_account_id kepada respons awam.
            $table->uuid('customer_account_id');
            $table->uuid('runner_account_id');

            // Julat rating sah ialah 1–5 bintang.
            $table->unsignedTinyInteger('rating');

            // Rating wajib, teks ulasan pilihan.
            $table->text('review')->nullable();

            $table->timestamps();

            // Hubungan kepada Core Task.
            $table->foreign(
                'task_id',
                'runner_task_ratings_task_fk'
            )
                ->references('task_id')
                ->on('core_tasks')
                ->restrictOnDelete();

            // Hubungan kepada pelanggan.
            $table->foreign(
                'customer_account_id',
                'runner_task_ratings_customer_fk'
            )
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();

            // Hubungan kepada runner.
            $table->foreign(
                'runner_account_id',
                'runner_task_ratings_runner_fk'
            )
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();

            $table->index(
                ['runner_account_id', 'created_at'],
                'runner_task_ratings_runner_created_index'
            );
        });

        // Hadkan rating kepada 1 hingga 5 pada peringkat database.
        DB::statement(
            'ALTER TABLE runner_task_ratings
             ADD CONSTRAINT '.self::CONSTRAINT_NAME.'
             CHECK (rating BETWEEN 1 AND 5)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_task_ratings');
    }
};
