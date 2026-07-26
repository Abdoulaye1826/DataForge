<?php

namespace App\Http\Controllers;

use App\Enums\ChartType;
use App\Models\Dataset;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\Visualization;
use App\Services\Visualization\VisualizationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Throwable;

class VisualizationController extends Controller
{
    public function __construct(private readonly VisualizationService $visualizationService)
    {
    }

    public function index(Project $project, Dataset $dataset, DatasetTable $table): View
    {
        $this->authorize('view', $project);

        return view('visualizations.index', [
            'project' => $project,
            'dataset' => $dataset,
            'table' => $table->load('columns'),
            'visualizations' => $this->visualizationService->forTable($table),
        ]);
    }

    public function store(Request $request, Project $project, Dataset $dataset, DatasetTable $table): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'chart_type' => ['required', new Enum(ChartType::class)],
        ]);

        $chartType = ChartType::from($request->input('chart_type'));

        try {
            $this->visualizationService->create(
                $table,
                $project,
                $request->input('name'),
                $chartType,
                $this->buildConfig($request),
            );
        } catch (Throwable $e) {
            return back()->withErrors(['visualization' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('projects.datasets.tables.visualizations.index', [$project, $dataset, $table])
            ->with('status', 'Visualisation créée.');
    }

    public function refresh(Project $project, Dataset $dataset, DatasetTable $table, Visualization $visualization): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->visualizationService->refresh($visualization, $table, $project);

        return back()->with('status', 'Visualisation actualisée.');
    }

    public function destroy(Project $project, Dataset $dataset, DatasetTable $table, Visualization $visualization): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->visualizationService->delete($visualization);

        return redirect()
            ->route('projects.datasets.tables.visualizations.index', [$project, $dataset, $table])
            ->with('status', 'Visualisation supprimée.');
    }

    private function buildConfig(Request $request): array
    {
        return array_filter([
            'x_column' => $request->input('x_column'),
            'y_column' => $request->input('y_column') ?: null,
            'category_column' => $request->input('category_column'),
            'value_column' => $request->input('value_column') ?: null,
            'value_columns' => $request->input('value_columns') ?: null,
            'column' => $request->input('column'),
            'columns' => $request->input('columns') ?: null,
            'aggregation' => $request->input('aggregation'),
            'bins' => $request->input('bins') ? (int) $request->input('bins') : null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
