<?php

namespace App\Repositories\Contracts;

use App\Models\StatisticalTest;
use Illuminate\Database\Eloquent\Collection;

interface StatisticalTestRepositoryInterface
{
    public function find(int $id): ?StatisticalTest;

    public function forTable(int $datasetTableId): Collection;

    public function create(array $attributes): StatisticalTest;

    public function delete(StatisticalTest $test): void;
}
