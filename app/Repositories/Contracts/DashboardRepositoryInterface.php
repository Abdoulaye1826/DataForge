<?php

namespace App\Repositories\Contracts;

use App\Models\Dashboard;
use Illuminate\Database\Eloquent\Collection;

interface DashboardRepositoryInterface
{
    public function find(int $id): ?Dashboard;

    public function allForProject(int $projectId): Collection;

    public function create(array $attributes): Dashboard;

    public function update(Dashboard $dashboard, array $attributes): Dashboard;

    public function delete(Dashboard $dashboard): void;
}
