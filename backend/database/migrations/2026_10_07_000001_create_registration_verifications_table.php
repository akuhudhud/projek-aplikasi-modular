<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20);
            $table->string('contact');
            $table->string('purpose', 40);
            $table->string('otp_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token_hash', 64)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'contact']);
            $table->index(['purpose', 'contact']);
            $table->index('expires_at');
            $table->index('verification_token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_verifications');
    }
};
