<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    public function find(int $id): ?Project;

    public function allForUser(int $userId): Collection;

    public function recentForUser(int $userId, int $limit = 5): Collection;

    public function countForUser(int $userId): int;

    public function create(array $attributes): Project;

    public function update(Project $project, array $attributes): Project;

    public function delete(Project $project): void;
}
