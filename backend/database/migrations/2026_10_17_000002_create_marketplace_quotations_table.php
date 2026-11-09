<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_quotations', function (Blueprint $table) {
            $table->uuid('quotation_id')->primary();

            $table->uuid('request_id');
            $table->uuid('runner_account_id');

            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('MYR');
            $table->text('message')->nullable();

            $table->string('status', 32)->default('SUBMITTED');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            // A quotation must always belong to an existing request.
            // Preserve quotation history; do not cascade-delete quotations.
            $table->foreign(
                'request_id',
                'marketplace_quotes_request_fk'
            )
                ->references('request_id')
                ->on('marketplace_requests')
                ->restrictOnDelete();

            // Preserve runner attribution for every submitted quotation.
            $table->foreign(
                'runner_account_id',
                'marketplace_quotes_runner_account_fk'
            )
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();

            $table->index(
                ['request_id', 'status', 'created_at'],
                'marketplace_quotes_request_status_index'
            );

            $table->index(
                ['runner_account_id', 'status', 'created_at'],
                'marketplace_quotes_runner_status_index'
            );

            $table->index(
                ['status', 'expires_at'],
                'marketplace_quotes_status_expiry_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_quotations');
    }
};
