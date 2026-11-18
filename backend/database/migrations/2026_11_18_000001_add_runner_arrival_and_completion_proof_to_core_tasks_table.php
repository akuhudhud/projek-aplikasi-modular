<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_tasks', function (Blueprint $table): void {
            $table->timestamp('arrived_at')->nullable();

            $table->decimal('arrival_latitude', 10, 7)->nullable();

            $table->decimal('arrival_longitude', 10, 7)->nullable();

            $table->string('completion_proof_path', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('core_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'arrived_at',
                'arrival_latitude',
                'arrival_longitude',
                'completion_proof_path',
            ]);
        });
    }
};
