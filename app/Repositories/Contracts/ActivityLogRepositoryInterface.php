<?php

namespace App\Repositories\Contracts;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Collection;

interface ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog;

    public function recentForUser(int $userId, int $limit = 10): Collection;
}
