<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driving_license_catalog', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('driving_license_type_id')
                ->constrained('driving_license_types')
                ->restrictOnDelete();

            $table->string('code', 30);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['driving_license_type_id', 'code'],
                'driving_license_type_class_code_unique'
            );

            $table->index(
                ['driving_license_type_id', 'is_active', 'sort_order'],
                'driving_license_class_active_sort_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driving_license_catalog');
    }
};
