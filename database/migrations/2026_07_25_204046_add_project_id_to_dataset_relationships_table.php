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
        Schema::table('dataset_relationships', function (Blueprint $table) {
            // Relationships now span across datasets (e.g. patients.csv +
            // staff.csv imported separately into the same project), so they
            // are scoped by project rather than by a single dataset.
            // dataset_id stays only for the case where source/target happen
            // to share one (e.g. sibling sheets of the same Excel file).
            $table->foreignId('project_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_relationships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->foreignId('dataset_id')->nullable(false)->change();
        });
    }
};
