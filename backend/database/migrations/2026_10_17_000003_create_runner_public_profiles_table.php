<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runner_public_profiles', function (Blueprint $table) {
            $table->uuid('profile_id')->primary();
            $table->uuid('account_id')->unique();
            $table->string('public_slug', 50)->unique();
            $table->string('display_name', 80);
            $table->text('bio')->nullable();
            $table->string('service_area', 120)->nullable();
            $table->json('services')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();

            $table->index(
                ['is_published', 'updated_at'],
                'runner_public_profiles_published_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_public_profiles');
    }
};
