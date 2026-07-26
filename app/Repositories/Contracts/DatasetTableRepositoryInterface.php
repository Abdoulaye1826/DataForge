<?php

namespace App\Repositories\Contracts;

use App\Models\DatasetTable;

interface DatasetTableRepositoryInterface
{
    public function find(int $id): ?DatasetTable;

    public function create(array $attributes): DatasetTable;

    public function update(DatasetTable $table, array $attributes): DatasetTable;
}
