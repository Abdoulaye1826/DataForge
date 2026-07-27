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
        Schema::create('visualization_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_table_id')->constrained()->cascadeOnDelete();
            $table->string('chart_type');
            $table->string('name');
            $table->json('config');
            $table->text('rationale');
            // Réutilise les mêmes valeurs que pipeline_suggestions.status
            // (pending/accepted/rejected) via App\Enums\PipelineSuggestionStatus -
            // même forme, pas besoin d'un enum dédié.
            $table->string('status')->default('pending');
            $table->timestamp('computed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visualization_suggestions');
    }
};
