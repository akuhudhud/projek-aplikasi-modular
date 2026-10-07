<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_verifications', function (Blueprint $table) {
            $table->timestamp('last_sent_at')->nullable()->after('attempts');
            $table->unsignedTinyInteger('resend_count')
                ->default(0)
                ->after('last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('registration_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'last_sent_at',
                'resend_count',
            ]);
        });
    }
};
