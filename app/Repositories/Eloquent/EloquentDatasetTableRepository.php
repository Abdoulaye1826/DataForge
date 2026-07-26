<?php

namespace App\Repositories\Eloquent;

use App\Models\DatasetTable;
use App\Repositories\Contracts\DatasetTableRepositoryInterface;

class EloquentDatasetTableRepository implements DatasetTableRepositoryInterface
{
    public function find(int $id): ?DatasetTable
    {
        return DatasetTable::find($id);
    }

    public function create(array $attributes): DatasetTable
    {
        return DatasetTable::create($attributes);
    }

    public function update(DatasetTable $table, array $attributes): DatasetTable
    {
        $table->update($attributes);

        return $table;
    }
}
