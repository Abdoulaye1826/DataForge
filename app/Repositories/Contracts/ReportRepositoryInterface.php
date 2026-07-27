<?php

namespace App\Repositories\Contracts;

use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;

interface ReportRepositoryInterface
{
    public function find(int $id): ?Report;

    public function forProject(int $projectId): Collection;

    public function countForUser(int $userId): int;

    /**
     * Every report across every project owned by the user, for the global
     * Rapports page.
     */
    public function allForUser(int $userId): Collection;

    public function create(array $attributes): Report;

    public function delete(Report $report): void;
}
