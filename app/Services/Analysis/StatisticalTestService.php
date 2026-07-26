<?php

namespace App\Services\Analysis;

use App\Enums\StatisticalTestType;
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

        $result = $this->pythonRunner->run('statistical_tests.py', [
            'storage_path' => $table->storage_path,
            'test_type' => $type->value,
            'config' => $config,
        ], $project->id);

        return $this->tests->create([
            'project_id' => $project->id,
            'dataset_table_id' => $table->id,
            'test_type' => $type,
            'config' => $config,
            'result' => $result->data,
            'computed_at' => now(),
        ]);
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
