<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_requests', function (Blueprint $table) {
            $table->uuid('request_id')->primary();

            $table->uuid('customer_account_id');
            $table->uuid('target_runner_account_id')->nullable();

            $table->string('source_type', 32)->default('marketplace');
            $table->string('task_class', 64);
            $table->text('description');

            $table->json('pickup_location_snapshot')->nullable();
            $table->json('delivery_location_snapshot')->nullable();
            $table->json('task_location_snapshot')->nullable();

            $table->string('status', 32)->default('OPEN');
            $table->uuid('winning_quotation_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(
                ['customer_account_id', 'status', 'created_at'],
                'marketplace_requests_customer_status_index'
            );

            $table->index(
                ['target_runner_account_id', 'status'],
                'marketplace_requests_target_status_index'
            );

            $table->index(
                ['status', 'expires_at'],
                'marketplace_requests_status_expiry_index'
            );

            $table->index(
                'winning_quotation_id',
                'marketplace_requests_winning_quote_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_requests');
    }
};
