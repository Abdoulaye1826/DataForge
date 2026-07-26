<?php

namespace App\Services\Analysis;

use App\Enums\StatisticalTestStatus;
use App\Enums\StatisticalTestType;
use App\Exceptions\PythonExecutionException;
use App\Jobs\RunStatisticalTestJob;
use App\Models\DatasetTable;
use App\Models\Project;
use App\Models\StatisticalTest;
use App\Repositories\Contracts\StatisticalTestRepositoryInterface;
use App\Services\Python\PythonRunnerService;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Module Analyse exploratoire: on-demand hypothesis testing
 * (statistical_tests.py) - unlike EDA/insights, this needs a human choice of
 * which columns/groups to compare, so it's triggered explicitly rather than
 * run automatically after import.
 *
 * Split so the actual Python work runs off the request thread: run()
 * validates and creates the row immediately as Pending and dispatches the
 * job; process() is what the job calls to do the real computation and
 * settle the row to Completed/Failed.
 */
class StatisticalTestService
{
    public function __construct(
        private readonly StatisticalTestRepositoryInterface $tests,
        private readonly PythonRunnerService $pythonRunner,
    ) {
    }

    public function forTable(DatasetTable $table): Collection
    {
        return $this->tests->forTable($table->id);
    }

    public function run(DatasetTable $table, Project $project, StatisticalTestType $type, array $config): StatisticalTest
    {
        $this->validateConfig($type, $config);

        $test = $this->tests->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'test_type' => $type,
            'config' => $config,
            'status' => StatisticalTestStatus::Pending,
            'computed_at' => null,
        ]);

        RunStatisticalTestJob::dispatch($test);

        return $test;
    }

    /**
     * Runs the real statistical_tests.py computation for a test already
     * recorded as Pending and settles it to Completed or Failed. Called by
     * RunStatisticalTestJob - never call this synchronously from a request.
     */
    public function process(StatisticalTest $test): void
    {
        try {
            $result = $this->pythonRunner->run('statistical_tests.py', [
                'storage_path' => $test->table->storage_path,
                'test_type' => $test->test_type->value,
                'config' => $test->config,
            ], $test->project_id);

            $this->tests->update($test, [
                'status' => StatisticalTestStatus::Completed,
                'result' => $result->data,
                'computed_at' => now(),
            ]);
        } catch (PythonExecutionException $e) {
            $this->tests->update($test, [
                'status' => StatisticalTestStatus::Failed,
                'error' => $e->getMessage(),
                'computed_at' => now(),
            ]);

            throw $e;
        }
    }

    public function delete(StatisticalTest $test): void
    {
        $this->tests->delete($test);
    }

    private function validateConfig(StatisticalTestType $type, array $config): void
    {
        foreach ($type->requiredFields() as $key) {
            if (! array_key_exists($key, $config) || $config[$key] === null || $config[$key] === '') {
                throw new InvalidArgumentException("Le champ « {$key} » est requis pour ce test.");
            }
        }
    }
}
