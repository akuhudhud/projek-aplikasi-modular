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
        Schema::table('sessions', function (Blueprint $table) {
            $table->timestamp('last_activity_at')
                ->nullable()
                ->after('created_at');

            $table->string('end_reason', 50)
                ->nullable()
                ->after('ended_at');

            $table->index('end_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex([
                'end_reason',
            ]);

            $table->dropColumn([
                'last_activity_at',
                'end_reason',
            ]);
        });
    }
};
