<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runner_license_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->unique();

            // Nombor lesen disimpan dalam bentuk encrypted.
            $table->text('license_number_encrypted');
            $table->string('license_class', 30);
            $table->date('expires_at');

            $table->string('status', 30)->default('PENDING')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewer_account_id')->nullable();
            $table->text('rejection_reason')->nullable();

            // Satu dokumen lesen bagi setiap permohonan.
            $table->string('disk', 30)->default('local');
            $table->string('document_path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_license_verifications');
    }
};
