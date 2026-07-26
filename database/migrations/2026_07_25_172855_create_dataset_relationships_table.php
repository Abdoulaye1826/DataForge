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
        Schema::create('dataset_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_table_id')->constrained('dataset_tables')->cascadeOnDelete();
            $table->foreignId('source_column_id')->constrained('dataset_columns')->cascadeOnDelete();
            $table->foreignId('target_table_id')->constrained('dataset_tables')->cascadeOnDelete();
            $table->foreignId('target_column_id')->constrained('dataset_columns')->cascadeOnDelete();
            $table->enum('relationship_type', ['one_to_one', 'one_to_many', 'many_to_many', 'unknown']);
            $table->float('confidence_score');
            $table->enum('status', ['suggested', 'confirmed', 'rejected'])->default('suggested');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_relationships');
    }
};
