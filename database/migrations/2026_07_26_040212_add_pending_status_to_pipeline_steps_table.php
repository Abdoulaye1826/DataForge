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
        // Raw SQL, not Blueprint::change() - Doctrine DBAL chokes on this
        // table's native MySQL ENUM column (step_type) as soon as it
        // introspects the table, even to alter unrelated columns.
        DB::statement("ALTER TABLE pipeline_steps MODIFY COLUMN status ENUM(
            'pending', 'applied', 'reverted', 'failed'
        ) NOT NULL DEFAULT 'applied'");

        DB::statement('ALTER TABLE pipeline_steps MODIFY COLUMN applied_at TIMESTAMP NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DELETE FROM pipeline_steps WHERE status = 'pending'");
        DB::statement("UPDATE pipeline_steps SET applied_at = created_at WHERE applied_at IS NULL");

        DB::statement('ALTER TABLE pipeline_steps MODIFY COLUMN applied_at TIMESTAMP NOT NULL');

        DB::statement("ALTER TABLE pipeline_steps MODIFY COLUMN status ENUM(
            'applied', 'reverted', 'failed'
        ) NOT NULL DEFAULT 'applied'");
    }
};
