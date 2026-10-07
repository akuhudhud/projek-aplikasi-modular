<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')
                ->nullable()
                ->after('phone');

            $table->timestamp('email_verified_at')
                ->nullable()
                ->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verified_at',
                'email_verified_at',
            ]);
        });
    }
};
