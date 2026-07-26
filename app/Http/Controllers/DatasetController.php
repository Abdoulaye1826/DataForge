<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonExecutionException;
use App\Exceptions\UnsupportedFileFormatException;
use App\Models\Dataset;
use App\Models\Project;
use App\Services\Import\DatasetImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DatasetController extends Controller
{
    public function __construct(private readonly DatasetImportService $datasetImport)
    {
    }

    public function show(Project $project, Dataset $dataset): View
    {
        $this->authorize('view', $project);

        return view('datasets.show', [
            'project' => $project,
            'dataset' => $dataset->load('tables.columns', 'tables.latestQualityReport', 'tables.aiInsights'),
        ]);
    }

    public function reimport(Project $project, Dataset $dataset): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $this->datasetImport->reimport($dataset, $project);
        } catch (PythonExecutionException|UnsupportedFileFormatException $e) {
            return back()->withErrors(['reimport' => $e->getMessage()]);
        }

        return redirect()
            ->route('projects.datasets.show', [$project, $dataset])
            ->with('status', 'Dataset retraité à partir du fichier original.');
    }

    public function destroy(Project $project, Dataset $dataset): RedirectResponse
    {
        $this->authorize('update', $project);

        $dataset->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Dataset supprimé.');
    }
}
