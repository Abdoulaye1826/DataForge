<?php

namespace App\Services\Import;

use App\Models\DatabaseConnection;
use App\Models\Project;
use App\Repositories\Contracts\DatabaseConnectionRepositoryInterface;
use App\Services\Python\PythonRunnerService;

/**
 * Module "Connecteurs SQL": lets a user plug DataForge directly into a live
 * PostgreSQL/MySQL database instead of exporting/uploading a file - saves the
 * connection (password encrypted at rest, see DatabaseConnection::$casts),
 * lists its tables on demand, and hands a chosen table to
 * DatasetImportService::importFromDatabase() to go through the exact same
 * onboarding cascade as a regular file import.
 */
class DatabaseConnectionService
{
    public function __construct(
        private readonly DatabaseConnectionRepositoryInterface $connections,
        private readonly PythonRunnerService $pythonRunner,
    ) {
    }

    /**
     * @throws \App\Exceptions\PythonExecutionException if the credentials
     *         can't reach the database - the caller decides whether to
     *         surface that as a form validation error.
     */
    public function create(Project $project, array $attributes): DatabaseConnection
    {
        $this->testConnection($attributes);

        return $this->connections->create([
            'project_id' => $project->id,
            'name' => $attributes['name'],
            'driver' => $attributes['driver'],
            'host' => $attributes['host'],
            'port' => $attributes['port'],
            'database' => $attributes['database'],
            'username' => $attributes['username'],
            'password' => $attributes['password'],
        ]);
    }

    public function delete(DatabaseConnection $connection): void
    {
        $this->connections->delete($connection);
    }

    /**
     * @return array<int, array{name: string, column_count: int, row_count: int}>
     */
    public function listTables(DatabaseConnection $connection): array
    {
        $result = $this->pythonRunner->run('db_list_tables.py', [
            'connection' => $this->connectionPayload($connection),
        ], $connection->project_id);

        return $result->data['tables'];
    }

    private function testConnection(array $attributes): void
    {
        $this->pythonRunner->run('db_list_tables.py', [
            'connection' => [
                'driver' => $attributes['driver'],
                'host' => $attributes['host'],
                'port' => $attributes['port'],
                'database' => $attributes['database'],
                'username' => $attributes['username'],
                'password' => $attributes['password'],
            ],
        ]);
    }

    /**
     * @return array{driver: string, host: string, port: int, database: string, username: string, password: string}
     */
    public function connectionPayload(DatabaseConnection $connection): array
    {
        return [
            'driver' => $connection->driver->value,
            'host' => $connection->host,
            'port' => $connection->port,
            'database' => $connection->database,
            'username' => $connection->username,
            'password' => $connection->password,
        ];
    }
}
