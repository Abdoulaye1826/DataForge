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
        Schema::create('user_pipeline_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('step_type');
            // Flat string form of "pattern" (e.g. "column_name:fax") so a (user, step_type,
            // pattern) triple can be looked up/uniquely constrained without indexing JSON.
            $table->string('pattern_key');
            $table->json('pattern');
            $table->unsignedInteger('times_applied')->default(1);
            $table->timestamp('last_applied_at');
            $table->timestamps();

            $table->unique(['user_id', 'step_type', 'pattern_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_pipeline_preferences');
    }
};
