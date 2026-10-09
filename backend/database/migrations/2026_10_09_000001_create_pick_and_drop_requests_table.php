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
        Schema::create('pick_and_drop_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('customer_account_id');

            $table->string('category', 30);
            $table->string('item_type', 30)->nullable();
            $table->text('item_description');

            $table->json('pickup_location_snapshot');
            $table->string('pickup_contact_name');
            $table->string('pickup_contact_phone', 30);

            $table->json('delivery_location_snapshot');
            $table->string('recipient_name');
            $table->string('recipient_phone', 30);

            $table->text('additional_instructions')->nullable();

            $table->string('status', 30)->default('SUBMITTED');

            $table->timestamps();

            $table->foreign('customer_account_id')
                ->references('id')
                ->on('accounts')
                ->restrictOnDelete();

            $table->index(
                ['customer_account_id', 'created_at'],
                'pnd_requests_customer_created_index'
            );

            $table->index(
                'status',
                'pnd_requests_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pick_and_drop_requests');
    }
};
