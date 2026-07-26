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
        Schema::table('dataset_columns', function (Blueprint $table) {
            $table->string('semantic_label')->nullable()->after('current_type');
            $table->text('semantic_reasoning')->nullable()->after('semantic_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_columns', function (Blueprint $table) {
            $table->dropColumn(['semantic_label', 'semantic_reasoning']);
        });
    }
};
