<?php

namespace App\Services\Analysis;

use App\Models\DatasetTable;
use App\Models\Project;
use App\Services\Python\PythonRunnerService;

/**
 * Module Compréhension: raw row browsing - every other view only shows
 * column-level stats/samples, but an analyst still needs to scroll/search
 * the actual data to spot-check a suspicious record. Delegates to
 * browse_table.py since the data only exists in the Parquet cache.
 */
class TableBrowserService
{
    public function __construct(private readonly PythonRunnerService $pythonRunner)
    {
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int} */
    public function browse(
        DatasetTable $table,
        Project $project,
        int $page = 1,
        int $perPage = 25,
        ?string $search = null,
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
    ): array {
        $result = $this->pythonRunner->run('browse_table.py', [
            'storage_path' => $table->storage_path,
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'sort_column' => $sortColumn,
            'sort_direction' => $sortDirection,
        ], $project->id);

        return $result->data;
    }
}
