<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Kekalkan UUID sebagai rekod sejarah.
            // Tiada FK supaya audit log tidak terpadam
            // apabila akaun actor atau target dipadam.
            $table->uuid('actor_account_id');
            $table->uuid('target_account_id');

            $table->string('action');
            $table->text('reason');
            $table->timestamps();

            $table->index('actor_account_id');
            $table->index('target_account_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
