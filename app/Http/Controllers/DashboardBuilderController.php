<?php

namespace App\Http\Controllers;

use App\Models\Dashboard;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\Visualization;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardBuilderController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
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

        $tables = DatasetTable::whereHas('dataset', fn ($query) => $query->where('project_id', $project->id))
            ->with(['dataset', 'columns'])
            ->get();

        $visualizations = Visualization::where('project_id', $project->id)->with('table')->get();

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
}
