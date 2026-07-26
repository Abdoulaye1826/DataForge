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
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_table_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('category', [
                'summary', 'key_points', 'anomaly', 'trend',
                'opportunity', 'risk', 'recommendation', 'conclusion',
            ]);
            $table->text('content');
            $table->enum('importance', ['low', 'medium', 'high'])->default('medium');
            $table->enum('generated_by', ['auto', 'on_demand'])->default('auto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
