<?php

namespace App\Http\Controllers;

use App\Enums\MlAnalysisType;
use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\MlAnalysis;
use App\Models\Project;
use App\Services\Analysis\MlAnalysisService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Throwable;

class MlAnalysisController extends Controller
{
    public function __construct(private readonly MlAnalysisService $mlAnalysis)
    {
    }

    public function show(Project $project, Dataset $dataset, DatasetTable $table): View
    {
        $this->authorize('view', $project);

        return view('ml.show', [
            'project' => $project,
            'dataset' => $dataset,
            'table' => $table->load('columns'),
            'analyses' => $this->mlAnalysis->forTable($table),
        ]);
    }

    public function store(Request $request, Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'analysis_type' => ['required', new Enum(MlAnalysisType::class)],
        ]);

        $type = MlAnalysisType::from($request->input('analysis_type'));

        try {
            $this->mlAnalysis->run($table, $project, $type, $this->buildConfig($type, $request));
        } catch (Throwable $e) {
            return back()->withErrors(['ml_analysis' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('projects.datasets.tables.ml.show', [$project, $dataset, $table])
            ->with('status', 'Analyse en cours de calcul...');
    }

    public function destroy(Project $project, Dataset $dataset, DatasetTable $table, MlAnalysis $mlAnalysis): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->mlAnalysis->delete($mlAnalysis);

        return redirect()
            ->route('projects.datasets.tables.ml.show', [$project, $dataset, $table])
            ->with('status', 'Analyse supprimée.');
    }

    private function buildConfig(MlAnalysisType $type, Request $request): array
    {
        return match ($type) {
            MlAnalysisType::Clustering => array_filter([
                'columns' => $request->input('columns', []),
                'n_clusters' => $request->input('n_clusters') ? (int) $request->input('n_clusters') : null,
            ]),
            MlAnalysisType::Forecast => array_filter([
                'date_column' => $request->input('date_column'),
                'value_column' => $request->input('value_column'),
                'periods' => $request->input('periods') ? (int) $request->input('periods') : null,
            ], fn ($value) => $value !== null && $value !== ''),
        };
    }
}
