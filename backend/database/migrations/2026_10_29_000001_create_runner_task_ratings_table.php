<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            // Julat sah 1–5 akan dikuatkuasakan oleh API.
            $table->unsignedTinyInteger('rating');

            // Rating wajib, teks ulasan pilihan.
            $table->text('review')->nullable();

            $table->timestamps();

            $table->index(
                ['runner_account_id', 'created_at'],
                'runner_task_ratings_runner_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_task_ratings');
    }
};
