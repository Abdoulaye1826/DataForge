<?php

namespace App\Repositories\Contracts;

use App\Models\DatasetRelationship;
use Illuminate\Database\Eloquent\Collection;

interface DatasetRelationshipRepositoryInterface
{
    public function find(int $id): ?DatasetRelationship;

    public function forProject(int $projectId): Collection;

    public function create(array $attributes): DatasetRelationship;

    public function deleteSuggestedForProject(int $projectId): void;

    public function updateStatus(DatasetRelationship $relationship, string $status): DatasetRelationship;
}
