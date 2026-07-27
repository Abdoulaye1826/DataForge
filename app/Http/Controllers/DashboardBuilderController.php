<?php

namespace App\Http\Controllers;

use App\Exceptions\PythonExecutionException;
use App\Models\Dashboard;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\Visualization;
use App\Services\Dashboard\DashboardExportService;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardBuilderController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly DashboardExportService $dashboardExport,
    ) {
    }

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('dashboards.index', [
            'project' => $project,
            'dashboards' => $this->dashboardService->allForProject($project),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $dashboard = $this->dashboardService->create($project, $request->input('name'));

        return redirect()->route('projects.dashboards.show', [$project, $dashboard]);
    }

    public function show(Project $project, Dashboard $dashboard): View
    {
        $this->authorize('view', $project);

        // Loaded once here so neither the widgets map below nor
        // DashboardService::filterableColumns() (which also walks
        // widget->visualization->table->columns) triggers a lazy load per
        // widget.
        $dashboard->load('widgets.visualization.table.columns');

        $tables = DatasetTable::whereHas('dataset', fn ($query) => $query->where('project_id', $project->id))
            ->with(['dataset', 'columns'])
            ->get();

        // whereHas('table') excludes visualizations whose source table was
        // since deleted (dataset_table_id nulls out via nullOnDelete() - the
        // row is kept for history, but it can't be picked for a new widget
        // since its data no longer exists), mirroring $tables above.
        $visualizations = Visualization::where('project_id', $project->id)->whereHas('table')->with('table')->get();

        $widgets = $dashboard->widgets->map(fn ($widget) => [
            'widget' => $widget,
            'data' => $this->dashboardService->renderData($widget),
        ]);

        return view('dashboards.show', [
            'project' => $project,
            'dashboard' => $dashboard,
            'widgets' => $widgets,
            'tables' => $tables,
            'visualizations' => $visualizations,
            'filterableColumns' => $this->dashboardService->filterableColumns($dashboard),
            'visualizationSuggestions' => $project->visualizationSuggestions()->pending()->with('table')->get(),
        ]);
    }

    public function duplicate(Project $project, Dashboard $dashboard): RedirectResponse
    {
        $this->authorize('update', $project);

        $copy = $this->dashboardService->duplicate($dashboard);

        return redirect()->route('projects.dashboards.show', [$project, $copy])->with('status', 'Dashboard dupliqué.');
    }

    public function destroy(Project $project, Dashboard $dashboard): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->dashboardService->delete($dashboard);

        return redirect()->route('projects.dashboards.index', $project)->with('status', 'Dashboard supprimé.');
    }

    /**
     * Génère un PDF du dashboard (widgets graphiques rasterisés en PNG,
     * KPI/tableaux/texte tels qu'affichés) et redirige directement vers son
     * téléchargement - stocké comme un Report ordinaire, visible aussi dans
     * "Rapports".
     */
    public function export(Project $project, Dashboard $dashboard): RedirectResponse
    {
        $this->authorize('update', $project);

        try {
            $report = $this->dashboardExport->generate($dashboard, $project);
        } catch (PythonExecutionException $e) {
            return back()->withErrors(['dashboard_export' => $e->getMessage()]);
        }

        return redirect()->route('projects.reports.download', [$project, $report]);
    }
}
