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

            // Akaun berkaitan; nullable untuk verification
            // yang belum dipautkan kepada akaun.
            $table->uuid('account_id')->nullable();

            $table->string('channel', 20);
            $table->string('contact');
            $table->string('purpose', 40);
            $table->string('otp_hash', 64);

            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);

            // Kawalan penghantaran semula OTP.
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedTinyInteger('resend_count')->default(0);

            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token_hash', 64)->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // Menandakan token telah digunakan.
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();

            // Hubungan akaun verification.
            $table->foreign(
                'account_id',
                'registration_verifications_account_fk'
            )
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();

            $table->index('account_id');
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
