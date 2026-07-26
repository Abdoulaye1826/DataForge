<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonExecutionException;
use App\Exceptions\UnsupportedFileFormatException;
use App\Http\Requests\ImportDatasetRequest;
use App\Models\Project;
use App\Services\Import\DatasetImportService;
use Illuminate\Http\RedirectResponse;

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
}
