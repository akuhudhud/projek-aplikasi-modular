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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('actor_account_id');
            $table->uuid('target_account_id');
            $table->string('action');
            $table->timestamps();

            $table->foreign('actor_account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();

            $table->foreign('target_account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();

            $table->index('actor_account_id');
            $table->index('target_account_id');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
