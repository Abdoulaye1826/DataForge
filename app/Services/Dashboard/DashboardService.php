<?php

namespace App\Services\Dashboard;

use App\Enums\WidgetType;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\DatasetColumn;
use App\Models\Project;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use App\Repositories\Contracts\DashboardWidgetRepositoryInterface;
use App\Services\Python\PythonRunnerService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Module Dashboard: CRUD for dashboards/widgets plus the read-side glue that
 * turns a widget's config into data the builder view can render - a chart
 * widget just points at its Visualization's cached data, a KPI widget reads
 * a single stat that's already sitting on DatasetColumn (no new computation
 * needed), a table widget shows a table's column summary, and a text widget
 * is just its own stored content.
 */
class DashboardService
{
    public function __construct(
        private readonly DashboardRepositoryInterface $dashboards,
        private readonly DashboardWidgetRepositoryInterface $widgets,
        private readonly PythonRunnerService $pythonRunner,
    ) {
    }

    public function allForProject(Project $project): Collection
    {
        return $this->dashboards->allForProject($project->id);
    }

    public function create(Project $project, string $name): Dashboard
    {
        return $this->dashboards->create([
            'project_id' => $project->id,
            'name' => $name,
        ]);
    }

    public function duplicate(Dashboard $dashboard): Dashboard
    {
        $copy = $this->dashboards->create([
            'project_id' => $dashboard->project_id,
            'name' => "{$dashboard->name} (copie)",
            'layout' => $dashboard->layout,
        ]);

        foreach ($dashboard->widgets as $widget) {
            $this->widgets->create([
                'dashboard_id' => $copy->id,
                'visualization_id' => $widget->visualization_id,
                'widget_type' => $widget->widget_type,
                'title' => $widget->title,
                'position_x' => $widget->position_x,
                'position_y' => $widget->position_y,
                'width' => $widget->width,
                'height' => $widget->height,
                'config' => $widget->config,
            ]);
        }

        return $copy;
    }

    public function delete(Dashboard $dashboard): void
    {
        $this->dashboards->delete($dashboard);
    }

    public function addWidget(
        Dashboard $dashboard,
        WidgetType $type,
        string $title,
        array $config,
        ?int $visualizationId = null,
    ): DashboardWidget {
        return $this->widgets->create([
            'dashboard_id' => $dashboard->id,
            'visualization_id' => $visualizationId,
            'widget_type' => $type,
            'title' => $title,
            'position_x' => 0,
            'position_y' => 0,
            'width' => 4,
            'height' => 3,
            'config' => $config,
        ]);
    }

    public function updateLayout(DashboardWidget $widget, int $x, int $y, int $width, int $height): DashboardWidget
    {
        return $this->widgets->update($widget, [
            'position_x' => $x,
            'position_y' => $y,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public function deleteWidget(DashboardWidget $widget): void
    {
        $this->widgets->delete($widget);
    }

    /**
     * Render-ready payload for a single widget, shape depends on widget_type:
     * - chart: {chart_type, data}
     * - kpi: {label, value}
     * - table: {columns: [{name, detected_type, null_percentage}, ...]}
     * - text: {content}
     */
    public function renderData(DashboardWidget $widget): array
    {
        return match ($widget->widget_type) {
            WidgetType::Chart => $this->chartData($widget),
            WidgetType::Kpi => $this->kpiData($widget),
            WidgetType::Table => $this->tableData($widget),
            WidgetType::Text => ['content' => $widget->config['content'] ?? ''],
        };
    }

    private function chartData(DashboardWidget $widget): array
    {
        $visualization = $widget->visualization;

        return [
            'chart_type' => $visualization?->chart_type->value,
            'data' => $visualization?->data_cache ?? [],
        ];
    }

    private function kpiData(DashboardWidget $widget): array
    {
        $column = DatasetColumn::where('dataset_table_id', $widget->config['dataset_table_id'] ?? null)
            ->where('name', $widget->config['column'] ?? null)
            ->first();

        $stat = $widget->config['stat'] ?? 'mean';
        $value = null;

        if ($column) {
            $value = match ($stat) {
                'null_count' => $column->null_count,
                'distinct_count' => $column->distinct_count,
                default => $column->stats[$stat] ?? null,
            };
        }

        return [
            'label' => $widget->config['label'] ?? $widget->title,
            'value' => $value,
        ];
    }

    private function tableData(DashboardWidget $widget): array
    {
        $columns = DatasetColumn::where('dataset_table_id', $widget->config['dataset_table_id'] ?? null)
            ->orderBy('position')
            ->limit(8)
            ->get(['name', 'detected_type', 'null_percentage']);

        return ['columns' => $columns];
    }

    /**
     * Module Dashboard "filtre global": re-runs generate_chart_data.py with
     * an extra filter, without touching the widget's persisted data_cache -
     * switching or clearing the dashboard filter never corrupts the default
     * (unfiltered) view a fresh page load would otherwise show.
     */
    public function liveChartData(DashboardWidget $widget, Project $project, ?array $filter): array
    {
        $visualization = $widget->visualization;

        if (! $visualization) {
            return ['chart_type' => null, 'data' => []];
        }

        $result = $this->pythonRunner->run('generate_chart_data.py', [
            'storage_path' => $visualization->table->storage_path,
            'chart_type' => $visualization->chart_type->value,
            'config' => $visualization->config,
            'filter' => $filter,
        ], $project->id);

        return ['chart_type' => $visualization->chart_type->value, 'data' => $result->data];
    }

    /**
     * Columns a dashboard's global filter bar can offer: the union of
     * categorical/boolean and date/datetime columns across every chart
     * widget's table, each tagged with which table(s) it belongs to so the
     * frontend only re-fetches widgets the filter actually applies to.
     *
     * @return array<int, array{name: string, type: string, table_ids: array<int, int>, values: array<int, string>}>
     */
    public function filterableColumns(Dashboard $dashboard): array
    {
        $columns = [];

        foreach ($dashboard->widgets as $widget) {
            if ($widget->widget_type !== WidgetType::Chart || ! $widget->visualization) {
                continue;
            }

            $table = $widget->visualization->table;

            foreach ($table->columns as $column) {
                $type = match ($column->detected_type->value) {
                    'category', 'boolean' => 'categorical',
                    'date', 'datetime' => 'date',
                    default => null,
                };

                if ($type === null) {
                    continue;
                }

                $columns[$column->name] ??= ['name' => $column->name, 'type' => $type, 'table_ids' => [], 'values' => []];
                $columns[$column->name]['table_ids'][] = $table->id;

                if ($type === 'categorical') {
                    $columns[$column->name]['values'] = array_values(array_unique(array_merge(
                        $columns[$column->name]['values'],
                        array_slice(array_keys($column->stats['top_values'] ?? []), 0, 30),
                    )));
                }
            }
        }

        foreach ($columns as &$column) {
            $column['table_ids'] = array_values(array_unique($column['table_ids']));
        }

        return array_values($columns);
    }
}
