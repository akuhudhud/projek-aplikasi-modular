<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_verifications', function (Blueprint $table) {
            $table->uuid('account_id')
                ->nullable()
                ->after('id');

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('registration_verifications', function (Blueprint $table) {
            $table->dropIndex([
                'account_id',
            ]);

            $table->dropColumn('account_id');
        });
    }
};
