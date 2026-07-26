<?php

namespace App\Services\Visualization;

use App\Enums\ChartType;
use App\Enums\VisualizationSource;
use App\Exceptions\PythonExecutionException;
use App\Jobs\GenerateVisualizationJob;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\Visualization;
use App\Repositories\Contracts\VisualizationRepositoryInterface;
use App\Services\Python\PythonRunnerService;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Module Visualisations: builds/refreshes chart data via generate_chart_data.py
 * and persists it as a Visualization the user can revisit without recomputing
 * (data_cache) - refresh() recomputes it after the underlying table changes.
 *
 * create() creates the row and dispatches the actual computation as a job
 * rather than running generate_chart_data.py inline - data_cache staying
 * null is the app's existing "not computed yet" signal (already relied on
 * by the report/dashboard code), process() is what the job calls to fill it.
 */
class VisualizationService
{
    /** @var array<string, string[]> Required config keys per chart type. */
    private const REQUIRED_CONFIG_KEYS = [
        'bar' => ['x_column'],
        'line' => ['x_column'],
        'pie' => ['category_column'],
        'donut' => ['category_column'],
        'scatter' => ['x_column', 'y_column'],
        'histogram' => ['column'],
        'heatmap' => [],
        'radar' => ['category_column', 'value_columns'],
        'treemap' => ['category_column'],
        'boxplot' => ['columns'],
    ];

    public function __construct(
        private readonly VisualizationRepositoryInterface $visualizations,
        private readonly PythonRunnerService $pythonRunner,
    ) {
    }

    public function forTable(DatasetTable $table): Collection
    {
        return $this->visualizations->forTable($table->id);
    }

    public function create(
        DatasetTable $table,
        Project $project,
        string $name,
        ChartType $chartType,
        array $config,
        VisualizationSource $source = VisualizationSource::UserCreated,
        ?string $rationale = null,
    ): Visualization {
        $this->validateConfig($chartType, $config);

        $visualization = $this->visualizations->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'name' => $name,
            'chart_type' => $chartType,
            'config' => $config,
            'source' => $source,
            'rationale' => $rationale,
        ]);

        GenerateVisualizationJob::dispatch($visualization);

        return $visualization;
    }

    /**
     * Dispatches (re)computation of a visualization's chart data - used both
     * right after create() and for the manual "recompute" action once the
     * underlying table has changed. Clears any previous data_cache/error
     * first so the UI immediately reflects "recalculating" instead of
     * showing stale data while the job runs.
     */
    public function refresh(Visualization $visualization): Visualization
    {
        $visualization = $this->visualizations->update($visualization, ['data_cache' => null, 'error' => null]);

        GenerateVisualizationJob::dispatch($visualization);

        return $visualization;
    }

    /**
     * Runs the real generate_chart_data.py computation for a visualization
     * and settles data_cache (success) or error (failure). Called by
     * GenerateVisualizationJob - never call this synchronously from a request.
     */
    public function process(Visualization $visualization): void
    {
        try {
            $result = $this->pythonRunner->run('generate_chart_data.py', [
                'storage_path' => $visualization->table->storage_path,
                'chart_type' => $visualization->chart_type->value,
                'config' => $visualization->config,
            ], $visualization->project_id);

            $this->visualizations->update($visualization, ['data_cache' => $result->data, 'error' => null]);
        } catch (PythonExecutionException $e) {
            $this->visualizations->update($visualization, ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function delete(Visualization $visualization): void
    {
        $this->visualizations->delete($visualization);
    }

    /**
     * Module Visualisations: "create automatically" - a small, sensible
     * starting set so a table's Visualisations page is never empty by the
     * time the user opens it: a histogram for the first numeric column, a
     * bar chart for the first categorical column, and a correlation heatmap
     * once there are enough numeric columns for one to mean anything.
     *
     * @return Collection<int, Visualization>
     */
    public function generateDefaults(DatasetTable $table, Project $project): Collection
    {
        $columns = $table->columns;
        $numeric = $columns->filter(fn ($column) => $column->detected_type->isNumeric());
        $categorical = $columns->filter(fn ($column) => $column->detected_type->value === 'category');

        $created = new Collection();

        if ($numeric->isNotEmpty()) {
            $column = $numeric->first();
            $created->push($this->create(
                $table,
                $project,
                "Distribution de {$column->name}",
                ChartType::Histogram,
                ['column' => $column->name],
                VisualizationSource::AutoGenerated,
                "Histogramme choisi car « {$column->name} » est une variable numérique continue : "
                    . "cette forme de graphique montre le mieux comment ses valeurs se répartissent.",
            ));
        }

        if ($categorical->isNotEmpty()) {
            $column = $categorical->first();
            $created->push($this->create(
                $table,
                $project,
                "Répartition de {$column->name}",
                ChartType::Bar,
                ['x_column' => $column->name, 'aggregation' => 'count'],
                VisualizationSource::AutoGenerated,
                "Graphique en barres choisi car « {$column->name} » est une variable catégorielle : "
                    . "il permet de comparer directement le nombre de lignes par catégorie.",
            ));
        }

        if ($numeric->count() >= 2) {
            $created->push($this->create(
                $table,
                $project,
                'Corrélations',
                ChartType::Heatmap,
                ['columns' => $numeric->pluck('name')->values()->all()],
                VisualizationSource::AutoGenerated,
                "Heatmap choisie car la table contient {$numeric->count()} colonnes numériques : "
                    . 'elle révèle en un coup d\'œil les corrélations entre toutes les paires de colonnes, '
                    . 'ce qu\'un graphique classique ne peut pas montrer au-delà de deux variables.',
            ));
        }

        return $created;
    }

    private function validateConfig(ChartType $chartType, array $config): void
    {
        foreach (self::REQUIRED_CONFIG_KEYS[$chartType->value] as $key) {
            if (! array_key_exists($key, $config) || $config[$key] === null || $config[$key] === '') {
                throw new InvalidArgumentException("Le champ « {$key} » est requis pour ce type de graphique.");
            }
        }
    }
}
