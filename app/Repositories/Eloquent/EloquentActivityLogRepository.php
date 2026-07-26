<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class EloquentActivityLogRepository implements ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog
    {
        return ActivityLog::create($attributes);
    }

    public function recentForUser(int $userId, int $limit = 10): Collection
    {
        return ActivityLog::where('user_id', $userId)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
