<?php

namespace App\Repositories\Contracts;

use App\Models\Report;
use Illuminate\Database\Eloquent\Collection;

interface ReportRepositoryInterface
{
    public function find(int $id): ?Report;

    public function forProject(int $projectId): Collection;

    public function create(array $attributes): Report;

    public function delete(Report $report): void;
}
