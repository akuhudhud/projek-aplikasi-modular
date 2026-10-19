<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_permissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_id');
            $table->string('permission_key', 100);
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();

            $table->unique(
                ['account_id', 'permission_key'],
                'account_permissions_account_key_unique'
            );

            $table->index('permission_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_permissions');
    }
};
