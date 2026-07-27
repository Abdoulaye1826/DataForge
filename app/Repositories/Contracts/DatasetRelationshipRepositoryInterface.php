<?php

namespace App\Repositories\Contracts;

use App\Models\DatasetRelationship;
use Illuminate\Database\Eloquent\Collection;

interface DatasetRelationshipRepositoryInterface
{
    public function find(int $id): ?DatasetRelationship;

    public function forProject(int $projectId): Collection;

    /**
     * Every relationship touching any of the given tables (as source or
     * target), regardless of which dataset the other side belongs to - for
     * the Dataset Intelligence report.
     *
     * @param array<int, int> $tableIds
     */
    public function forTables(array $tableIds): Collection;

    public function create(array $attributes): DatasetRelationship;

    public function deleteSuggestedForProject(int $projectId): void;

    public function updateStatus(DatasetRelationship $relationship, string $status): DatasetRelationship;
}
