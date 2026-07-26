<?php

namespace App\Repositories\Eloquent;

use App\Models\Dataset;
use App\Repositories\Contracts\DatasetRepositoryInterface;

class EloquentDatasetRepository implements DatasetRepositoryInterface
{
    public function find(int $id): ?Dataset
    {
        return Dataset::find($id);
    }

    public function create(array $attributes): Dataset
    {
        return Dataset::create($attributes);
    }

    public function update(Dataset $dataset, array $attributes): Dataset
    {
        $dataset->update($attributes);

        return $dataset;
    }

    public function countForUser(int $userId): int
    {
        return Dataset::whereHas('project', fn ($query) => $query->where('user_id', $userId))->count();
    }
}
