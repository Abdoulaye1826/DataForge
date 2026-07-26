<?php

namespace App\Services\Analysis;

use App\Enums\GeneratedBy;
use App\Exceptions\AiProviderException;
use App\Models\Analysis;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Repositories\Contracts\AnalysisRepositoryInterface;
use App\Services\Ai\AiInsightService;
use App\Services\Python\PythonRunnerService;
use Illuminate\Support\Facades\Log;

/**
 * Module Analyse exploratoire: runs eda.py on a table and stores the full
 * result (descriptive stats, correlation matrix, histograms, boxplots,
 * distributions) as a single Analysis record. A new row is written on every
 * run (never overwritten), same convention as QualityReport, so re-running
 * after cleaning/preprocessing keeps history instead of losing it.
 *
 * Also triggers Module Insights automatically right after ("after each
 * analysis, the AI generates...") - guarded so a missing/broken AI provider
 * never breaks the EDA run itself, since insights are a bonus on top, not a
 * dependency of it.
 */
class ExploratoryAnalysisService
{
    public function __construct(
        private readonly AnalysisRepositoryInterface $analyses,
        private readonly PythonRunnerService $pythonRunner,
        private readonly AiInsightService $aiInsights,
    ) {
    }

    public function runForTable(DatasetTable $table, Project $project): Analysis
    {
        $result = $this->pythonRunner->run('eda.py', [
            'storage_path' => $table->storage_path,
        ], $project->id);

        $analysis = $this->analyses->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'results' => $result->data,
            'computed_at' => now(),
        ]);

        $this->generateInsightsSafely($table, $project, $analysis);

        return $analysis;
    }

    private function generateInsightsSafely(DatasetTable $table, Project $project, Analysis $analysis): void
    {
        try {
            $this->aiInsights->generateForTable($table, $project, $analysis, GeneratedBy::Auto);
        } catch (AiProviderException $e) {
            Log::warning("Auto insight generation skipped for table {$table->id}: {$e->getMessage()}");
        }
    }
}
