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
        Schema::table('pipeline_suggestions', function (Blueprint $table) {
            // Module transparence du plan (inspiré d'OctOpus) : le
            // raisonnement global de l'IA pour tout le lot de suggestions,
            // dupliqué sur chaque ligne du même lot plutôt qu'une table à
            // part - un lot ne dépasse jamais 8 lignes, la redondance est
            // négligeable et ça évite un modèle dédié pour un simple texte.
            $table->text('plan')->nullable()->after('rationale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_suggestions', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
