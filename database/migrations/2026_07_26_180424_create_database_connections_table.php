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
        Schema::create('database_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('driver', ['pgsql', 'mysql']);
            $table->string('host');
            $table->unsignedSmallInteger('port');
            $table->string('database');
            $table->string('username');
            // Chiffré au repos via le cast Eloquent 'encrypted' (APP_KEY) -
            // jamais stocké ni journalisé en clair (voir PythonRunnerService,
            // qui redirige input/output vers des fichiers temporaires
            // supprimés après chaque appel, jamais vers les logs applicatifs).
            $table->text('password');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_connections');
    }
};
