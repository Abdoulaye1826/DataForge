<?php

namespace App\Repositories\Eloquent;

use App\Models\StatisticalTest;
use App\Repositories\Contracts\StatisticalTestRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentStatisticalTestRepository implements StatisticalTestRepositoryInterface
{
    public function find(int $id): ?StatisticalTest
    {
        return StatisticalTest::find($id);
    }

    public function forTable(int $datasetTableId): Collection
    {
        return StatisticalTest::where('dataset_table_id', $datasetTableId)->latest('id')->get();
    }

    public function create(array $attributes): StatisticalTest
    {
        return StatisticalTest::create($attributes);
    }

    public function update(StatisticalTest $test, array $attributes): StatisticalTest
    {
        $test->update($attributes);

        return $test;
    }

    public function delete(StatisticalTest $test): void
    {
        $test->delete();
    }
}
