<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->unique();
            $table->string('real_name');
            $table->text('mykad_number_encrypted');
            $table->string('status', 30)->default('PENDING')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();

            // Keep reviewer identity as a historical UUID.
            // This avoids deleting verification history if an admin account
            // is later removed from the accounts table.
            $table->uuid('reviewer_account_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Identity records must be explicitly removed before an account
            // can be physically deleted.
            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();
        });

        Schema::create('identity_verification_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('identity_verification_id');
            $table->string('disk', 30)->default('local');
            $table->string('path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->foreign('identity_verification_id')
                ->references('id')
                ->on('identity_verifications')
                ->cascadeOnDelete();

            $table->index('identity_verification_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_verification_documents');
        Schema::dropIfExists('identity_verifications');
    }
};
