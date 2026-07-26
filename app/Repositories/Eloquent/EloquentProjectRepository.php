<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function find(int $id): ?Project
    {
        return Project::find($id);
    }

    public function allForUser(int $userId): Collection
    {
        return Project::where('user_id', $userId)
            ->withCount('datasets')
            ->orderByDesc('last_activity_at')
            ->get();
    }

    public function recentForUser(int $userId, int $limit = 5): Collection
    {
        return Project::where('user_id', $userId)
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    public function countForUser(int $userId): int
    {
        return Project::where('user_id', $userId)->count();
    }

    public function create(array $attributes): Project
    {
        return Project::create($attributes);
    }

    public function update(Project $project, array $attributes): Project
    {
        $project->update($attributes);

        return $project;
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}
