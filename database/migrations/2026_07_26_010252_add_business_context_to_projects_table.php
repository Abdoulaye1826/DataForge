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
        Schema::table('projects', function (Blueprint $table) {
            $table->enum('domain', [
                'finance', 'marketing', 'hr', 'health', 'commerce',
                'logistics', 'industry', 'research', 'administration', 'other',
            ])->nullable()->after('description');
            $table->string('domain_other')->nullable()->after('domain');

            $table->enum('objective', [
                'dashboard', 'understand_sales', 'detect_anomalies', 'forecast',
                'segment_customers', 'clean_data', 'build_report', 'explore', 'other',
            ])->nullable()->after('domain_other');
            $table->string('objective_other')->nullable()->after('objective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['domain', 'domain_other', 'objective', 'objective_other']);
        });
    }
};
