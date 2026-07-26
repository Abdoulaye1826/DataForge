<?php

namespace App\Services\Pipeline;

use App\Exceptions\AiProviderException;
use App\Exceptions\PythonExecutionException;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Ai\SemanticColumnService;
use App\Services\Analysis\ExploratoryAnalysisService;
use App\Services\Python\PythonRunnerService;
use App\Services\Quality\DataQualityService;
use App\Services\Visualization\VisualizationService;
use Illuminate\Support\Facades\Log;

/**
 * Everything read-only that needs to happen the moment a table's cache
 * exists - column detection, quality audit, exploratory analysis (+ AI
 * insights in cascade), a handful of default charts - regardless of how
 * that table came to be: a fresh import, a re-import, or a join between
 * two existing tables. Shared so every entry point gets the same "fully
 * understood with zero clicks" treatment for free.
 */
class TableOnboardingService
{
    public function __construct(
        private readonly PythonRunnerService $pythonRunner,
        private readonly DatasetColumnSyncService $columnSync,
        private readonly DataQualityService $dataQuality,
        private readonly ExploratoryAnalysisService $exploratoryAnalysis,
        private readonly VisualizationService $visualizations,
        private readonly SemanticColumnService $semanticColumns,
    ) {
    }

    public function onboard(DatasetTable $table, Project $project): void
    {
        $this->analyzeStructure($table, $project);
        $this->dataQuality->generate($table, $project);
        $this->generateSemanticLabelsSafely($table, $project);
        $this->exploratoryAnalysis->runForTable($table, $project);
        $this->generateDefaultVisualizationsSafely($table, $project);
    }

    private function analyzeStructure(DatasetTable $table, Project $project): void
    {
        $result = $this->pythonRunner->run('analyze_structure.py', [
            'storage_path' => $table->storage_path,
        ], $project->id);

        $this->columnSync->sync($table, $result->data['columns']);
    }

    /**
     * Default charts are a bonus on top, not a dependency - a Python failure
     * here (e.g. an unusual column shape) must never turn an otherwise good
     * table into an error.
     */
    private function generateDefaultVisualizationsSafely(DatasetTable $table, Project $project): void
    {
        try {
            $this->visualizations->generateDefaults($table, $project);
        } catch (PythonExecutionException $e) {
            Log::warning("Default visualization generation skipped for table {$table->id}: {$e->getMessage()}");
        }
    }

    /**
     * Semantic labels are a readability bonus, not a dependency - a missing/
     * broken AI provider must never block onboarding, same guard pattern as
     * Module Insights.
     */
    private function generateSemanticLabelsSafely(DatasetTable $table, Project $project): void
    {
        try {
            $this->semanticColumns->generateForTable($table, $project);
        } catch (AiProviderException $e) {
            Log::warning("Semantic column labeling skipped for table {$table->id}: {$e->getMessage()}");
        }
    }
}
