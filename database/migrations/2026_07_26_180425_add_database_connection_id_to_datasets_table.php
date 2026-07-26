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
        Schema::table('datasets', function (Blueprint $table) {
            // Un dataset importé via un connecteur SQL n'a pas de fichier
            // uploadé (disk_path/size_bytes restent null/0, comme pour une
            // table jointe) mais garde une référence à la connexion pour
            // pouvoir se re-synchroniser depuis reimport().
            $table->foreignId('database_connection_id')->nullable()->after('project_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('database_connection_id');
        });
    }
};
