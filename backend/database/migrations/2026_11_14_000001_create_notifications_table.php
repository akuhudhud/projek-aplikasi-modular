<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            // ID unik setiap notifikasi.
            $table->uuid('id')->primary();

            // Jenis notifikasi.
            $table->string('type', 255);

            // Penerima notifikasi menggunakan UUID Account.
            $table->uuidMorphs('notifiable');

            // Kandungan notifikasi dalam format JSON.
            $table->json('data');

            // Status bacaan.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Memudahkan pembersihan notifikasi lama.
            $table->index(
                'created_at',
                'notifications_created_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
