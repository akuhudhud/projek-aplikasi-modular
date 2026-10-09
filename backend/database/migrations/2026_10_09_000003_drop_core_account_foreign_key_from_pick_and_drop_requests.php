<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_and_drop_requests', function (Blueprint $table) {
            $table->dropForeign(['customer_account_id']);
        });
    }

    public function down(): void
    {
        Schema::table('pick_and_drop_requests', function (Blueprint $table) {
            $table->foreign('customer_account_id')
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();
        });
    }
};
