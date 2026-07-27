<?php

namespace App\Repositories\Contracts;

use App\Models\Dataset;
use Illuminate\Database\Eloquent\Collection;

interface DatasetRepositoryInterface
{
    public function find(int $id): ?Dataset;

    public function create(array $attributes): Dataset;

    public function update(Dataset $dataset, array $attributes): Dataset;

    public function countForUser(int $userId): int;

    /**
     * Every dataset across every project owned by the user, for the global
     * Datasets page.
     */
    public function allForUser(int $userId): Collection;
}
