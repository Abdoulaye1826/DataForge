<?php

namespace App\Services\Analysis;

use App\Enums\MlAnalysisStatus;
use App\Enums\MlAnalysisType;
use App\Exceptions\PythonExecutionException;
use App\Jobs\RunMlAnalysisJob;
use App\Models\DatasetTable;
use App\Models\MlAnalysis;
use App\Models\Project;
use App\Repositories\Contracts\MlAnalysisRepositoryInterface;
use App\Services\Python\PythonRunnerService;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Module Machine Learning: on-demand K-Means segmentation and linear trend
 * forecasting (ml_analysis.py) - like statistical tests, this needs a human
 * choice of columns/parameters, so it's triggered explicitly rather than
 * run automatically after import.
 *
 * Split so the actual Python work (which can be slow on a large table) runs
 * off the request thread: run() validates and creates the row immediately
 * as Pending and dispatches the job; process() is what the job calls to do
 * the real computation and settle the row to Completed/Failed.
 */
class MlAnalysisService
{
    public function __construct(
        private readonly MlAnalysisRepositoryInterface $analyses,
        private readonly PythonRunnerService $pythonRunner,
    ) {
    }

    public function forTable(DatasetTable $table): Collection
    {
        return $this->analyses->forTable($table->id);
    }

    public function run(DatasetTable $table, Project $project, MlAnalysisType $type, array $config): MlAnalysis
    {
        $this->validateConfig($type, $config);

        $analysis = $this->analyses->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'analysis_type' => $type,
            'config' => $config,
            'status' => MlAnalysisStatus::Pending,
            'computed_at' => null,
        ]);

        RunMlAnalysisJob::dispatch($analysis);

        return $analysis;
    }

    /**
     * Runs the real ml_analysis.py computation for an analysis already
     * recorded as Pending and settles it to Completed or Failed. Called by
     * RunMlAnalysisJob - never call this synchronously from a request.
     */
    public function process(MlAnalysis $analysis): void
    {
        try {
            $result = $this->pythonRunner->run('ml_analysis.py', [
                'storage_path' => $analysis->table->storage_path,
                'analysis_type' => $analysis->analysis_type->value,
                'config' => $analysis->config,
            ], $analysis->project_id);

            $this->analyses->update($analysis, [
                'status' => MlAnalysisStatus::Completed,
                'result' => $result->data,
                'computed_at' => now(),
            ]);
        } catch (PythonExecutionException $e) {
            $this->analyses->update($analysis, [
                'status' => MlAnalysisStatus::Failed,
                'error' => $e->getMessage(),
                'computed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function delete(MlAnalysis $analysis): void
    {
        $this->analyses->delete($analysis);
    }

    private function validateConfig(MlAnalysisType $type, array $config): void
    {
        foreach ($type->requiredFields() as $key) {
            if (! array_key_exists($key, $config) || $config[$key] === null || $config[$key] === '' || $config[$key] === []) {
                throw new InvalidArgumentException("Le champ « {$key} » est requis pour cette analyse.");
            }
        }
    }
}
