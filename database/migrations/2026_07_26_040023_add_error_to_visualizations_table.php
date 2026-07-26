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
        Schema::table('visualizations', function (Blueprint $table) {
            // No separate "pending" status needed: data_cache already being
            // nullable is an existing, already-relied-upon "not computed
            // yet" signal (see the report/dashboard code that filters on
            // it) - this column only adds the "why it's still empty" half.
            $table->text('error')->nullable()->after('data_cache');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visualizations', function (Blueprint $table) {
            $table->dropColumn('error');
        });
    }
};
