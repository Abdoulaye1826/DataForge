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
        // Beaucoup de bases locales/dev (ex. root sans mot de passe) ont un
        // mot de passe réellement vide - la colonne doit l'accepter.
        Schema::table('database_connections', function (Blueprint $table) {
            $table->text('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('database_connections', function (Blueprint $table) {
            $table->text('password')->nullable(false)->change();
        });
    }
};
