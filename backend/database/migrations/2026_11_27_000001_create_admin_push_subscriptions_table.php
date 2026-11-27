<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('account_id');

            // Push endpoint provided by the browser's Push service.
            $table->text('endpoint');

            // SHA-256 hash allows endpoint uniqueness without indexing TEXT.
            $table->char('endpoint_hash', 64)->unique();

            // Browser subscription encryption keys.
            $table->text('public_key');
            $table->text('auth_token');

            $table->string('content_encoding', 30)
                ->default('aes128gcm');

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();

            $table->index(
                'account_id',
                'admin_push_subscriptions_account_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_push_subscriptions');
    }
};
