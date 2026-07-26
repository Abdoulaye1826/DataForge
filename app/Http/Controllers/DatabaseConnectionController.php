<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonExecutionException;
use App\Http\Requests\StoreDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use App\Models\Project;
use App\Services\Import\DatabaseConnectionService;
use App\Services\Import\DatasetImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DatabaseConnectionController extends Controller
{
    public function __construct(
        private readonly DatabaseConnectionService $connections,
        private readonly DatasetImportService $datasetImport,
    ) {
    }

    public function store(StoreDatabaseConnectionRequest $request, Project $project): RedirectResponse
    {
        try {
            $this->connections->create($project, $request->validated());
        } catch (PythonExecutionException $e) {
            return back()->withErrors(['connection' => "Impossible de se connecter : {$e->getMessage()}"])->withInput();
        }

        return redirect()->route('projects.show', $project)
            ->with('status', 'Connexion à la base de données enregistrée.');
    }

    public function destroy(Project $project, DatabaseConnection $connection): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->connections->delete($connection);

        return redirect()->route('projects.show', $project)
            ->with('status', 'Connexion supprimée.');
    }

    /**
     * Liste les tables distantes d'une connexion enregistrée, pour peupler le
     * sélecteur d'import - voir resources/js/database-connection.js.
     */
    public function tables(Project $project, DatabaseConnection $connection): JsonResponse
    {
        $this->authorize('view', $project);

        try {
            return response()->json(['tables' => $this->connections->listTables($connection)]);
        } catch (PythonExecutionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function import(Request $request, Project $project, DatabaseConnection $connection): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate(['table_name' => ['required', 'string']]);

        // Défense en profondeur : le nom de table vient d'un <select> rempli
        // par tables() ci-dessus, mais on revérifie côté serveur qu'il existe
        // bien parmi les tables réelles de la connexion avant de le
        // transmettre à db_import_table.py.
        $tableNames = array_column($this->connections->listTables($connection), 'name');

        if (! in_array($request->input('table_name'), $tableNames, true)) {
            return back()->withErrors(['table_name' => 'Cette table n\'existe pas (ou plus) sur cette connexion.']);
        }

        $this->datasetImport->importFromDatabase($project, $connection, $request->input('table_name'));

        $project->touchActivity();

        return redirect()->route('projects.show', $project)
            ->with('status', "Import de la table « {$request->input('table_name')} » lancé.");
    }
}
