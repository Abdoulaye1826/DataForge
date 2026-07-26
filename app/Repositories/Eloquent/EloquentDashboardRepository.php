<?php

namespace App\Repositories\Eloquent;

use App\Models\Dashboard;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentDashboardRepository implements DashboardRepositoryInterface
{
    public function find(int $id): ?Dashboard
    {
        return Dashboard::find($id);
    }

    public function allForProject(int $projectId): Collection
    {
        return Dashboard::where('project_id', $projectId)->latest()->get();
    }

    public function create(array $attributes): Dashboard
    {
        return Dashboard::create($attributes);
    }

    public function update(Dashboard $dashboard, array $attributes): Dashboard
    {
        $dashboard->update($attributes);

        return $dashboard;
    }

    public function delete(Dashboard $dashboard): void
    {
        $dashboard->delete();
    }
}
