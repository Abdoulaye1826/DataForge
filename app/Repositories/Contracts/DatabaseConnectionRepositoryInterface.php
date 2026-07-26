<?php

namespace App\Repositories\Contracts;

use App\Models\DatabaseConnection;
use Illuminate\Database\Eloquent\Collection;

interface DatabaseConnectionRepositoryInterface
{
    public function find(int $id): ?DatabaseConnection;

    public function forProject(int $projectId): Collection;

    public function create(array $attributes): DatabaseConnection;

    public function delete(DatabaseConnection $connection): void;
}
