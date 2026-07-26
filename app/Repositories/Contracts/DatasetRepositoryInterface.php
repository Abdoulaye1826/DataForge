<?php

namespace App\Repositories\Contracts;

use App\Models\Dataset;

interface DatasetRepositoryInterface
{
    public function find(int $id): ?Dataset;

    public function create(array $attributes): Dataset;

    public function update(Dataset $dataset, array $attributes): Dataset;

    public function countForUser(int $userId): int;
}
