<?php

namespace App\Http\Controllers;

use App\Enums\WidgetType;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Project;
use App\Models\Visualization;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class DashboardWidgetController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function store(Request $request, Project $project, Dashboard $dashboard): RedirectResponse
    {
        $this->authorize('update', $project);

        $request->validate(['widget_type' => ['required', new Enum(WidgetType::class)]]);

        $type = WidgetType::from($request->input('widget_type'));
        [$title, $config, $visualizationId] = $this->buildWidget($type, $request);

        $this->dashboardService->addWidget($dashboard, $type, $title, $config, $visualizationId);

        return redirect()
            ->route('projects.dashboards.show', [$project, $dashboard])
            ->with('status', 'Widget ajouté.');
    }

    public function update(Request $request, Project $project, Dashboard $dashboard, DashboardWidget $widget): JsonResponse
    {
        $this->authorize('update', $project);

        $request->validate([
            'x' => ['required', 'integer', 'min:0'],
            'y' => ['required', 'integer', 'min:0'],
            'w' => ['required', 'integer', 'min:1'],
            'h' => ['required', 'integer', 'min:1'],
        ]);

        $this->dashboardService->updateLayout(
            $widget,
            $request->integer('x'),
            $request->integer('y'),
            $request->integer('w'),
            $request->integer('h'),
        );

        return response()->json(['success' => true]);
    }

    public function destroy(Project $project, Dashboard $dashboard, DashboardWidget $widget): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->dashboardService->deleteWidget($widget);

        return redirect()
            ->route('projects.dashboards.show', [$project, $dashboard])
            ->with('status', 'Widget supprimé.');
    }

    /**
     * Module Dashboard "filtre global": live (non-persisted) chart data for
     * one widget, optionally filtered - backs the dashboard filter bar's
     * fetch-and-redraw without touching the widget's saved data_cache.
     */
    public function data(Request $request, Project $project, Dashboard $dashboard, DashboardWidget $widget): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'filter_column' => ['nullable', 'string', 'max:255'],
            'filter_operator' => ['nullable', 'in:eq,between'],
            'filter_value' => ['nullable', 'string', 'max:255'],
            'filter_start' => ['nullable', 'date'],
            'filter_end' => ['nullable', 'date'],
        ]);

        $filter = null;
        if ($request->filled('filter_column') && $request->filled('filter_operator')) {
            $filter = array_filter([
                'column' => $request->input('filter_column'),
                'operator' => $request->input('filter_operator'),
                'value' => $request->input('filter_value'),
                'start' => $request->input('filter_start'),
                'end' => $request->input('filter_end'),
            ], fn ($value) => $value !== null && $value !== '');
        }

        return response()->json($this->dashboardService->liveChartData($widget, $project, $filter));
    }

    /**
     * @return array{0: string, 1: array, 2: int|null}
     */
    private function buildWidget(WidgetType $type, Request $request): array
    {
        return match ($type) {
            WidgetType::Chart => $this->buildChartWidget($request),
            WidgetType::Kpi => [
                $request->input('label', 'Indicateur'),
                [
                    'dataset_table_id' => (int) $request->input('kpi_table_id'),
                    'column' => $request->input('kpi_column'),
                    'stat' => $request->input('stat', 'mean'),
                    'label' => $request->input('label'),
                ],
                null,
            ],
            WidgetType::Table => [
                $request->input('label', 'Résumé de table'),
                ['dataset_table_id' => (int) $request->input('table_table_id')],
                null,
            ],
            WidgetType::Text => [
                $request->input('label', 'Note'),
                ['content' => $request->input('content', '')],
                null,
            ],
        };
    }

    private function buildChartWidget(Request $request): array
    {
        $visualization = Visualization::findOrFail($request->input('visualization_id'));

        return [$visualization->name, [], $visualization->id];
    }
}
