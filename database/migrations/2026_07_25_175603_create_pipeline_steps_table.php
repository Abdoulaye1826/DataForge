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
        Schema::create('pipeline_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_table_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('step_order');
            $table->enum('step_type', [
                'import', 'rename_column', 'drop_column', 'merge', 'split', 'filter',
                'create_column', 'convert_type', 'encode', 'normalize', 'standardize',
                'categorize', 'dedupe', 'fix_dates', 'trim_spaces', 'fix_case', 'custom',
            ]);
            $table->string('label');
            $table->json('params')->nullable();
            $table->enum('status', ['applied', 'reverted', 'failed'])->default('applied');
            $table->integer('rows_affected')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['project_id', 'step_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_steps');
    }
};
