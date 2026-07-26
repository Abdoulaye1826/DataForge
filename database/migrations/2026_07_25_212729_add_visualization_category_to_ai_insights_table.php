<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Raw SQL, not Blueprint::change() - Doctrine DBAL chokes on MySQL
        // native ENUM columns (it has no type mapping for them) as soon as
        // it introspects a table that contains one.
        DB::statement("ALTER TABLE ai_insights MODIFY COLUMN category ENUM(
            'summary', 'key_points', 'anomaly', 'trend',
            'opportunity', 'risk', 'recommendation', 'conclusion', 'visualization'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DELETE FROM ai_insights WHERE category = 'visualization'");

        DB::statement("ALTER TABLE ai_insights MODIFY COLUMN category ENUM(
            'summary', 'key_points', 'anomaly', 'trend',
            'opportunity', 'risk', 'recommendation', 'conclusion'
        ) NOT NULL");
    }
};
