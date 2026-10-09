<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pick_and_drop_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('request_id')->unique();

            $table->string('status', 30)->default('AVAILABLE');

            $table->timestamps();

            $table->foreign('request_id')
                ->references('id')
                ->on('pick_and_drop_requests')
                ->restrictOnDelete();

            $table->index(
                'status',
                'pnd_tasks_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pick_and_drop_tasks');
    }
};
