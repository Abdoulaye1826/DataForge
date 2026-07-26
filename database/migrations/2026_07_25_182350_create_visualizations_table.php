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
        Schema::create('visualizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('chart_type', [
                'bar', 'line', 'pie', 'donut', 'scatter', 'histogram',
                'heatmap', 'radar', 'treemap', 'boxplot',
            ]);
            $table->json('config');
            $table->json('data_cache')->nullable();
            $table->enum('source', ['auto_generated', 'user_created', 'ai_recommended'])->default('user_created');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visualizations');
    }
};
