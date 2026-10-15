<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_tasks', function (Blueprint $table) {
            $table->uuid('task_id')->primary();

            $table->string('module_key', 100);
            $table->string('task_type', 100);
            $table->string('source_type', 100);
            $table->string('source_id', 191);

            $table->uuid('created_by_account_id')->nullable();
            $table->uuid('assigned_account_id')->nullable();

            $table->string('generic_status', 32)->default('OPEN');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['module_key', 'task_type', 'source_id'],
                'core_tasks_source_identity_unique'
            );

            $table->index(
                ['generic_status', 'created_at'],
                'core_tasks_status_created_index'
            );

            $table->index(
                ['assigned_account_id', 'generic_status'],
                'core_tasks_assignee_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_tasks');
    }
};
