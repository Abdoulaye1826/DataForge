<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonExecutionException;
use App\Exceptions\UnsupportedFileFormatException;
use App\Http\Requests\ImportDatasetRequest;
use App\Models\Project;
use App\Services\Import\DatasetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class DatasetImportController extends Controller
{
    public function __construct(private readonly DatasetImportService $datasetImport)
    {
    }

    public function store(ImportDatasetRequest $request, Project $project): RedirectResponse
    {
        $imported = 0;
        $errors = [];

        foreach ($request->file('files') as $file) {
            try {
                $this->datasetImport->import($project, $file);
                $imported++;
            } catch (UnsupportedFileFormatException|PythonExecutionException $e) {
                $errors[] = "{$file->getClientOriginalName()} : {$e->getMessage()}";
            }
        }

        $project->touchActivity();

        if ($errors === []) {
            return redirect()->route('projects.show', $project)
                ->with('status', $imported > 1 ? "{$imported} datasets importés avec succès." : 'Dataset importé avec succès.');
        }

        return redirect()->route('projects.show', $project)
            ->with('status', $imported > 0 ? "{$imported} dataset(s) importé(s)." : null)
            ->with('errors_import', $errors);
    }

    /**
     * Onboarding sans friction : importe un des jeux de données bundlés avec
     * l'application (voir config('dataforge.demo_datasets')) en le faisant
     * passer par le même DatasetImportService::import() qu'un vrai upload -
     * un UploadedFile "test" pointant sur le fichier bundlé est indiscernable
     * d'un vrai fichier envoyé par le navigateur pour le reste du pipeline.
     */
    public function importDemo(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'dataset' => ['required', Rule::in(array_keys(config('dataforge.demo_datasets')))],
        ]);

        $demo = config('dataforge.demo_datasets.' . $request->input('dataset'));
        $file = new UploadedFile($demo['file'], basename($demo['file']), 'text/csv', null, true);

        try {
            $this->datasetImport->import($project, $file);
        } catch (UnsupportedFileFormatException|PythonExecutionException $e) {
            return back()->withErrors(['demo' => $e->getMessage()]);
        }

        $project->touchActivity();

        return redirect()->route('projects.show', $project)
            ->with('status', "Jeu de données « {$demo['label']} » importé.");
    }
}
