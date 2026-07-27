<?php

namespace App\Http\Controllers;

use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Analysis\ExploratoryAnalysisService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AnalysisController extends Controller
{
    public function __construct(private readonly ExploratoryAnalysisService $exploratoryAnalysis)
    {
    }

    public function show(Project $project, Dataset $dataset, DatasetTable $table): View
    {
        $this->authorize('view', $project);

        // aiInsights.table.dataset + aiInsights.project are for
        // <x-insight-action-button>, which builds a route per insight.
        $table->load('columns', 'aiInsights.table.dataset', 'aiInsights.project');

        return view('analysis.show', [
            'project' => $project,
            'dataset' => $dataset,
            'table' => $table,
            'analysis' => $table->latestAnalysis,
            'insights' => $table->aiInsights,
            'statisticalTests' => $table->statisticalTests,
        ]);
    }

    public function run(Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->exploratoryAnalysis->runForTable($table, $project);

        return redirect()
            ->route('projects.datasets.tables.analysis.show', [$project, $dataset, $table])
            ->with('status', 'Analyse exploratoire générée.');
    }
}
