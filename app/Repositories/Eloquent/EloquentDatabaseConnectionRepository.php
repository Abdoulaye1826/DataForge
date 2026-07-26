<?php

namespace App\Repositories\Eloquent;

use App\Models\DatabaseConnection;
use App\Repositories\Contracts\DatabaseConnectionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentDatabaseConnectionRepository implements DatabaseConnectionRepositoryInterface
{
    public function find(int $id): ?DatabaseConnection
    {
        return DatabaseConnection::find($id);
    }

    public function forProject(int $projectId): Collection
    {
        return DatabaseConnection::where('project_id', $projectId)->latest()->get();
    }

    public function create(array $attributes): DatabaseConnection
    {
        return DatabaseConnection::create($attributes);
    }

    public function delete(DatabaseConnection $connection): void
    {
        $connection->delete();
    }
}
