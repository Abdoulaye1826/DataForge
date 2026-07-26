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
        Schema::create('dataset_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_table_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('original_name');
            $table->unsignedInteger('position');
            $table->enum('detected_type', ['integer', 'float', 'string', 'date', 'datetime', 'boolean', 'category', 'unknown']);
            $table->enum('current_type', ['integer', 'float', 'string', 'date', 'datetime', 'boolean', 'category', 'unknown']);
            $table->boolean('is_useless')->default(false);
            $table->unsignedBigInteger('null_count')->default(0);
            $table->float('null_percentage')->default(0);
            $table->unsignedBigInteger('distinct_count')->default(0);
            $table->json('sample_values')->nullable();
            $table->json('stats')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_columns');
    }
};
