<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('statistical_tests', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('test_type');
            $table->text('error')->nullable()->after('result');
        });

        // Raw SQL, not Blueprint::change() - Doctrine DBAL chokes on this
        // table's native MySQL ENUM column (test_type) as soon as it
        // introspects the table, even to alter an unrelated column.
        DB::statement('ALTER TABLE statistical_tests MODIFY COLUMN result JSON NULL');
        DB::statement('ALTER TABLE statistical_tests MODIFY COLUMN computed_at TIMESTAMP NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DELETE FROM statistical_tests WHERE result IS NULL');

        DB::statement('ALTER TABLE statistical_tests MODIFY COLUMN result JSON NOT NULL');
        DB::statement('ALTER TABLE statistical_tests MODIFY COLUMN computed_at TIMESTAMP NOT NULL');

        Schema::table('statistical_tests', function (Blueprint $table) {
            $table->dropColumn(['status', 'error']);
        });
    }
};
