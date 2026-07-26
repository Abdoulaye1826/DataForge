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
        // it introspects a table that contains one, even to alter an
        // unrelated column.
        DB::statement("ALTER TABLE datasets MODIFY COLUMN format ENUM(
            'csv', 'xlsx', 'json', 'parquet', 'sql', 'derived'
        ) NOT NULL");

        // A derived (joined) dataset has no uploaded source file to point at.
        DB::statement('ALTER TABLE datasets MODIFY COLUMN disk_path VARCHAR(255) NULL');

        DB::statement("ALTER TABLE pipeline_steps MODIFY COLUMN step_type ENUM(
            'import', 'rename_column', 'drop_column', 'merge', 'split', 'filter',
            'create_column', 'convert_type', 'encode', 'normalize', 'standardize',
            'categorize', 'dedupe', 'fix_dates', 'trim_spaces', 'fix_case', 'join', 'custom'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DELETE FROM datasets WHERE format = 'derived'");

        DB::statement("ALTER TABLE datasets MODIFY COLUMN format ENUM(
            'csv', 'xlsx', 'json', 'parquet', 'sql'
        ) NOT NULL");

        DB::statement('ALTER TABLE datasets MODIFY COLUMN disk_path VARCHAR(255) NOT NULL');

        DB::statement("DELETE FROM pipeline_steps WHERE step_type = 'join'");

        DB::statement("ALTER TABLE pipeline_steps MODIFY COLUMN step_type ENUM(
            'import', 'rename_column', 'drop_column', 'merge', 'split', 'filter',
            'create_column', 'convert_type', 'encode', 'normalize', 'standardize',
            'categorize', 'dedupe', 'fix_dates', 'trim_spaces', 'fix_case', 'custom'
        ) NOT NULL");
    }
};
