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
            $table->uuid('account_id');

            $table->string('license_number', 100);
            $table->string('license_type', 30);
            $table->string('license_class', 30);
            $table->date('expires_at');

            $table->string('status', 30)
                ->default('PENDING')
                ->index();

            $table->boolean('is_current')
                ->default(false);

            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewer_account_id')->nullable();
            $table->text('rejection_reason')->nullable();

            // Satu dokumen lesen bagi setiap rekod permohonan.
            $table->string('disk', 30)->default('local');
            $table->string('document_path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();

            $table->index(
                ['account_id', 'is_current'],
                'runner_license_account_current_index'
            );

            $table->index(['status', 'submitted_at']);

            $table->index(
                ['account_id', 'license_type', 'license_class', 'status'],
                'runner_license_type_class_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_license_verifications');
    }
};
