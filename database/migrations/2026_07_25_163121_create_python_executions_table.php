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
        Schema::create('python_executions', function (Blueprint $table) {
            $table->id();
            // project_id is added by a later migration once the projects table exists (Phase 1).
            $table->string('script_name');
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->enum('status', ['success', 'error']);
            $table->unsignedInteger('duration_ms');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('python_executions');
    }
};
